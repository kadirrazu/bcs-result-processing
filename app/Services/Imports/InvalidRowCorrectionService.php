<?php

namespace App\Services\Imports;

use App\Models\ImportCorrectionEntry;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

/**
 * Shared correction workflow for pre-approval import staging rows.
 *
 * Only rows that are currently invalid or identity-conflicted may be changed.
 * Valid and warning rows are deliberately protected from correction uploads.
 */
final class InvalidRowCorrectionService
{
    /** @return array{table:string,headers:list<string>,log_channel:string} */
    public function definition(string $module): array
    {
        return match ($module) {
            'registration' => [
                'table' => 'registration_import_staging',
                'headers' => array_values((array) config('registrations.headers', [])),
                'log_channel' => 'registration',
            ],
            'preliminary' => [
                'table' => 'preliminary_import_staging',
                'headers' => array_values((array) config('preliminary.headers', [])),
                'log_channel' => 'preliminary',
            ],
            'written' => [
                'table' => 'written_import_staging',
                'headers' => array_values((array) config('written.headers', [])),
                'log_channel' => 'written',
            ],
            'viva_mapping' => [
                'table' => 'viva_mapping_import_staging',
                'headers' => array_values((array) config('viva.mapping_headers', [])),
                'log_channel' => 'viva',
            ],
            'viva_board' => [
                'table' => 'viva_board_import_staging',
                'headers' => array_values((array) config('viva.board_headers', [])),
                'log_channel' => 'viva',
            ],
            default => throw new RuntimeException('Unsupported import correction module.'),
        };
    }

    public function createCorrectionWorkbook(string $module, int $batchId, string $path): int
    {
        $definition = $this->definition($module);
        $rows = DB::connection('exam')->table($definition['table'])
            ->where('batch_id', $batchId)
            ->whereIn('validation_status', ['invalid', 'identity_conflict'])
            ->orderBy('source_row')
            ->get();

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_merge(['source_row'], $definition['headers'])));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_merge(
                [(string) $row->source_row],
                array_values($this->sourcePayload($module, $row)),
            )));
        }

        $writer->close();

        return $rows->count();
    }

    /**
     * Apply a correction file atomically and preserve immutable before/after payloads.
     * Revalidation is queued by the calling controller after this method succeeds.
     *
     * @return array{corrected_rows:int,source_rows:list<int>}
     */
    public function apply(string $module, object $batch, UploadedFile $file, User $actor): array
    {
        if (! in_array((string) $batch->status, ['validated', 'failed', 'approved'], true)) {
            throw ValidationException::withMessages([
                'correction_file' => 'Corrections are available after validation has identified invalid rows. Finish any running import step first.',
            ]);
        }

        $definition = $this->definition($module);
        $entries = $this->readCorrectionFile($file, $definition['headers']);

        if ($entries === []) {
            throw ValidationException::withMessages(['correction_file' => 'The correction file does not contain any data rows.']);
        }

        $sourceRows = array_column($entries, 'source_row');
        if (count($sourceRows) !== count(array_unique($sourceRows))) {
            throw ValidationException::withMessages(['correction_file' => 'The correction file contains the same source row more than once.']);
        }

        $currentRows = DB::connection('exam')->table($definition['table'])
            ->where('batch_id', (int) $batch->id)
            ->whereIn('source_row', $sourceRows)
            ->get()
            ->keyBy('source_row');

        $problems = [];
        foreach ($entries as $entry) {
            $sourceRow = $entry['source_row'];
            $current = $currentRows->get($sourceRow);
            if ($current === null) {
                $problems[] = "Source row {$sourceRow} does not belong to this import batch.";
                continue;
            }
            if (! in_array((string) $current->validation_status, ['invalid', 'identity_conflict'], true)) {
                $problems[] = "Source row {$sourceRow} is no longer invalid and cannot be changed through a correction upload.";
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages([
                'correction_file' => array_slice($problems, 0, 10),
            ]);
        }

        $timestamp = now();
        DB::connection('exam')->transaction(function () use (
            $module, $batch, $entries, $currentRows, $definition, $actor, $file, $timestamp
        ): void {
            foreach ($entries as $entry) {
                $sourceRow = $entry['source_row'];
                $current = $currentRows->get($sourceRow);
                $correctedPayload = $entry['payload'];
                $originalPayload = $this->sourcePayload($module, $current);

                ImportCorrectionEntry::query()->create([
                    'module' => $module,
                    'batch_id' => (int) $batch->id,
                    'staging_row_id' => (int) $current->id,
                    'source_row' => (int) $sourceRow,
                    'validation_status_before' => (string) $current->validation_status,
                    'original_payload' => $originalPayload,
                    'corrected_payload' => $correctedPayload,
                    'source_filename' => $file->getClientOriginalName(),
                    'actor_id' => (int) $actor->id,
                    'actor_name' => (string) $actor->name,
                    'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                    'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
                    'created_at' => $timestamp,
                ]);

                DB::connection('exam')->table($definition['table'])
                    ->where('id', $current->id)
                    ->update($this->stagingUpdate($module, $current, $correctedPayload, $timestamp->format('Y-m-d H:i:s')));
            }
        });

        Log::channel($definition['log_channel'])->info('Invalid import rows corrected and queued for validation.', [
            'module' => $module,
            'batch_id' => (int) $batch->id,
            'corrected_rows' => count($entries),
            'source_rows' => $sourceRows,
            'actor_id' => (int) $actor->id,
            'actor_name' => (string) $actor->name,
            'source_filename' => $file->getClientOriginalName(),
        ]);

        return ['corrected_rows' => count($entries), 'source_rows' => array_values($sourceRows)];
    }

    /** @param list<string> $headers @return list<array{source_row:int,payload:array<string,mixed>}> */
    private function readCorrectionFile(UploadedFile $file, array $headers): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $reader = match ($extension) {
            'xlsx' => new XlsxReader(),
            'csv' => new CsvReader(),
            default => throw ValidationException::withMessages(['correction_file' => 'Use an XLSX or CSV correction file.']),
        };

        $reader->open($file->getRealPath());
        $expected = array_merge(['source_row'], $headers);
        $entries = [];
        $rowNumber = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $spreadsheetRow) {
                    $rowNumber++;
                    $values = $spreadsheetRow->toArray();
                    if ($rowNumber === 1) {
                        $actual = array_map(
                            static fn (mixed $value): string => strtolower(trim((string) $value)),
                            array_slice(array_pad($values, count($expected), null), 0, count($expected)),
                        );
                        if ($actual !== $expected) {
                            throw ValidationException::withMessages([
                                'correction_file' => 'The correction file headers were changed. Please use the correction workbook downloaded from this batch.',
                            ]);
                        }
                        continue;
                    }

                    if ($this->isEmptyRow($values, count($expected))) {
                        continue;
                    }

                    $values = array_slice(array_pad($values, count($expected), null), 0, count($expected));
                    $mapped = array_combine($expected, $values);
                    $sourceRowText = trim((string) ($mapped['source_row'] ?? ''));
                    if (preg_match('/^\d+$/', $sourceRowText) !== 1 || (int) $sourceRowText < 2) {
                        throw ValidationException::withMessages([
                            'correction_file' => "Correction file row {$rowNumber} has an invalid source_row value.",
                        ]);
                    }

                    $payload = [];
                    foreach ($headers as $header) {
                        $payload[$header] = $this->sourceText($mapped[$header] ?? null);
                    }

                    $entries[] = ['source_row' => (int) $sourceRowText, 'payload' => $payload];
                }
                break;
            }
        } finally {
            try { $reader->close(); } catch (Throwable) {}
        }

        return $entries;
    }

    /** @return array<string,mixed> */
    private function sourcePayload(string $module, object $row): array
    {
        if ($module === 'registration') {
            return [
                'user' => $row->user_id,
                'reg' => $row->reg,
                'name' => $row->name,
                'fname' => $row->father_name,
                'mname' => $row->mother_name,
                'b_date' => $row->raw_birth_date,
                'ssc_roll' => $row->ssc_roll,
                'ssc_year' => $row->ssc_year,
                'hsc_roll' => $row->hsc_roll,
                'hsc_year' => $row->hsc_year,
                'graduation_year' => $row->graduation_year,
                'sex' => $row->sex_code,
                'district' => $row->district_code,
                'university' => $row->university_code,
                'b_subject' => $row->bachelor_subject_code,
                'post_related_subject' => $row->post_related_subject_code,
                'has_ff_quota' => $row->has_ff_quota,
                'has_em_quota' => $row->has_em_quota,
                'has_phc_quota' => $row->has_phc_quota,
                'name_bn' => $row->name_bn,
                'fname_bn' => $row->father_name_bn,
                'mname_bn' => $row->mother_name_bn,
                'national_id' => $row->national_id,
                'cadre_category' => $row->cadre_category,
                'status' => $row->candidate_status,
                'comment' => $row->comment,
            ];
        }

        if ($module === 'preliminary') {
            return [
                'user' => $row->raw_user ?? $row->user_id,
                'reg' => $row->raw_reg ?? $row->reg,
                'mark' => $row->raw_mark,
                'candidate_status' => $row->raw_candidate_status,
            ];
        }

        if ($module === 'viva_board') {
            $payload = is_array($row->raw_payload ?? null) ? $row->raw_payload : (json_decode((string) ($row->raw_payload ?? ''), true) ?: []);
            $result = []; foreach ((array) config('viva.board_headers', []) as $header) { $result[$header] = $payload[$header] ?? null; } return $result;
        }

        if ($module === 'viva_mapping') {
            $payload = is_array($row->raw_payload ?? null)
                ? $row->raw_payload
                : (json_decode((string) ($row->raw_payload ?? ''), true) ?: []);
            return [
                'user' => $payload['user'] ?? $row->user_id,
                'reg' => $payload['reg'] ?? $row->reg,
                'code' => $payload['code'] ?? $row->code,
            ];
        }

        $payload = is_array($row->raw_payload ?? null)
            ? $row->raw_payload
            : (json_decode((string) ($row->raw_payload ?? ''), true) ?: []);
        $result = [];
        foreach ((array) config('written.headers', []) as $header) {
            $result[$header] = $payload[$header] ?? null;
        }
        return $result;
    }

    /** @return array<string,mixed> */
    private function stagingUpdate(string $module, object $current, array $payload, string $timestamp): array
    {
        if ($module === 'registration') {
            $ff = $this->plain($payload['has_ff_quota'] ?? null);
            $em = $this->plain($payload['has_em_quota'] ?? null);
            $phc = $this->plain($payload['has_phc_quota'] ?? null);

            return [
                'user_id' => strtoupper($this->plain($payload['user'] ?? null)),
                'reg' => $this->plain($payload['reg'] ?? null),
                'national_id' => $this->nullable($payload['national_id'] ?? null),
                'name' => $this->nullable($payload['name'] ?? null),
                'father_name' => $this->nullable($payload['fname'] ?? null),
                'mother_name' => $this->nullable($payload['mname'] ?? null),
                'name_bn' => $this->nullable($payload['name_bn'] ?? null),
                'father_name_bn' => $this->nullable($payload['fname_bn'] ?? null),
                'mother_name_bn' => $this->nullable($payload['mname_bn'] ?? null),
                'raw_birth_date' => $this->nullable($payload['b_date'] ?? null),
                'birth_date' => null,
                'ssc_roll' => $this->nullable($payload['ssc_roll'] ?? null),
                'ssc_year' => $this->nullable($payload['ssc_year'] ?? null),
                'hsc_roll' => $this->nullable($payload['hsc_roll'] ?? null),
                'hsc_year' => $this->nullable($payload['hsc_year'] ?? null),
                'graduation_year' => $this->nullable($payload['graduation_year'] ?? null),
                'sex_code' => $this->nullable($payload['sex'] ?? null),
                'district_code' => $this->nullable($payload['district'] ?? null),
                'division_code' => null,
                'university_code' => $this->nullable($payload['university'] ?? null),
                'bachelor_subject_code' => $this->nullable($payload['b_subject'] ?? null),
                'post_related_subject_code' => $this->nullable($payload['post_related_subject'] ?? null),
                'cadre_category' => $this->nullable($payload['cadre_category'] ?? null),
                'has_ff_quota' => $ff === '' ? null : $ff,
                'has_em_quota' => $em === '' ? null : $em,
                'has_phc_quota' => $phc === '' ? null : $phc,
                'has_quota' => $this->hasQuota($ff, $em, $phc),
                'candidate_status' => strtolower($this->plain($payload['status'] ?? null) ?: 'active'),
                'comment' => $this->nullable($payload['comment'] ?? null),
                'registration_id' => null,
                'validation_status' => 'pending',
                'validation_errors' => null,
                'validation_warnings' => null,
                'updated_at' => $timestamp,
            ];
        }

        if ($module === 'preliminary') {
            return [
                'raw_user' => $this->nullable($payload['user'] ?? null),
                'raw_reg' => $this->nullable($payload['reg'] ?? null),
                'raw_mark' => $this->nullable($payload['mark'] ?? null),
                'raw_candidate_status' => $this->nullable($payload['candidate_status'] ?? null),
                'registration_id' => null,
                'user_id' => $this->normalizeUser($payload['user'] ?? null),
                'reg' => $this->normalizeReg($payload['reg'] ?? null),
                'mark' => null,
                'candidate_status' => null,
                'validation_status' => 'pending',
                'validation_errors' => null,
                'validation_warnings' => null,
                'updated_at' => $timestamp,
            ];
        }

        if ($module === 'viva_board') {
            $raw=[]; foreach ((array) config('viva.board_headers', []) as $header) { $raw[$header]=$this->nullable($payload[$header] ?? null); }
            $flag=static fn($v): bool => trim((string)($v ?? '')) !== '';
            return ['raw_payload'=>json_encode($raw, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'viva_candidate_mapping_id'=>null,'registration_id'=>null,'code'=>$this->normalizeCode($payload['code']??null),'raw_viva_date'=>$this->nullable($payload['viva_date']??null),'viva_date'=>null,'member_id'=>$this->nullable($payload['member_id']??null),'raw_mark'=>$this->nullable($payload['mark']??null),'mark'=>null,'attendance_status'=>null,'raw_viva_cff'=>$this->nullable($payload['viva_cff']??null),'raw_viva_em'=>$this->nullable($payload['viva_em']??null),'raw_viva_phc'=>$this->nullable($payload['viva_phc']??null),'viva_cff'=>$flag($payload['viva_cff']??null),'viva_em'=>$flag($payload['viva_em']??null),'viva_phc'=>$flag($payload['viva_phc']??null),'raw_invalid_flag'=>$this->nullable($payload['invalid']??null),'raw_issue_flag'=>$this->nullable($payload['issue']??null),'invalid_flag'=>$flag($payload['invalid']??null),'issue_flag'=>$flag($payload['issue']??null),'validation_status'=>'pending','validation_errors'=>null,'validation_warnings'=>null,'updated_at'=>$timestamp];
        }

        if ($module === 'viva_mapping') {
            $raw = [
                'user' => $this->nullable($payload['user'] ?? null),
                'reg' => $this->nullable($payload['reg'] ?? null),
                'code' => $this->nullable($payload['code'] ?? null),
            ];
            return [
                'raw_payload' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'registration_id' => null,
                'written_result_id' => null,
                'user_id' => $this->normalizeUser($payload['user'] ?? null),
                'reg' => $this->normalizeReg($payload['reg'] ?? null),
                'code' => $this->normalizeCode($payload['code'] ?? null),
                'validation_status' => 'pending',
                'validation_errors' => null,
                'validation_warnings' => null,
                'updated_at' => $timestamp,
            ];
        }

        $raw = [];
        foreach ((array) config('written.headers', []) as $header) {
            $raw[$header] = $this->nullable($payload[$header] ?? null);
        }
        $prsRaw = $this->nullable($payload['prs_mark'] ?? null);

        return [
            'raw_payload' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'registration_id' => null,
            'user_id' => $this->normalizeUser($payload['user'] ?? null),
            'reg' => $this->normalizeReg($payload['reg'] ?? null),
            'normalized_marks' => null,
            'prs_code' => $this->normalizeCode($payload['prs_code'] ?? null),
            'prs_mark' => is_numeric($prsRaw) ? (float) $prsRaw : null,
            'data_source_note' => $this->nullable($payload['data_source_note'] ?? null),
            'status' => (string) ($current->status ?? 'active'),
            'validation_status' => 'pending',
            'validation_errors' => null,
            'validation_warnings' => null,
            'updated_at' => $timestamp,
        ];
    }

    /** @param array<int,mixed> $values */
    private function isEmptyRow(array $values, int $columnCount): bool
    {
        foreach (array_slice($values, 0, $columnCount) as $value) {
            if ($value instanceof DateTimeInterface || ($value !== null && trim((string) $value) !== '')) {
                return false;
            }
        }
        return true;
    }

    private function sourceText(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('dmY');
        }
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function plain(mixed $value): string
    {
        return $this->sourceText($value) ?? '';
    }

    private function nullable(mixed $value): ?string
    {
        $value = $this->sourceText($value);
        return $value === '' ? null : $value;
    }

    private function normalizeUser(mixed $value): ?string
    {
        $value = strtoupper(trim((string) ($value ?? '')));
        return $value === '' ? null : $value;
    }

    private function normalizeReg(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if (str_ends_with($value, '.0')) {
            $value = substr($value, 0, -2);
        }
        return $value === '' ? null : $value;
    }

    private function normalizeCode(mixed $value): ?string
    {
        $value = strtoupper(trim((string) ($value ?? '')));
        if (preg_match('/^\d+\.0$/', $value) === 1) {
            $value = substr($value, 0, -2);
        }
        return $value === '' ? null : $value;
    }

    private function hasQuota(string $ff, string $em, string $phc): bool
    {
        foreach ([$ff, $em, $phc] as $value) {
            if ($value !== '' && is_numeric($value) && (int) $value > 0) {
                return true;
            }
        }
        return false;
    }
}
