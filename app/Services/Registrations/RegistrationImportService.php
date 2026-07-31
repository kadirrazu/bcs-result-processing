<?php

namespace App\Services\Registrations;

use App\Jobs\ProcessRegistrationImport;
use App\Models\RegistrationImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Queue and process registration spreadsheets using one forward-only stream.
 *
 * The workbook is opened once. Rows are validated and persisted in bulk, so a
 * 375,000-row XLSX is not reparsed hundreds of times and does not execute one
 * INSERT/SELECT pair per candidate.
 */
final class RegistrationImportService
{
    public function __construct(
        private readonly RegistrationMasterMap $maps,
        private readonly RegistrationRowNormalizer $normalizer,
        private readonly RegistrationRowValidator $validator,
        private readonly RegistrationUniversityCodePolicy $universityPolicy,
    ) {}

    public function enqueue(UploadedFile $file, int $userId, int $examinationId): RegistrationImportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = sprintf(
            'registration-imports/%s-%s.%s',
            now()->format('YmdHis'),
            bin2hex(random_bytes(8)),
            $extension,
        );
        $file->storeAs(dirname($storedName), basename($storedName), 'local');

        $batch = RegistrationImportBatch::query()->create([
            'examination_id' => $examinationId,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'status' => 'queued',
            'chunk_size' => max(500, (int) config('registrations.chunk_size', 2000)),
            'queued_at' => now(),
            'created_by' => $userId,
        ]);

        ProcessRegistrationImport::dispatch($examinationId, $batch->id);

        return $batch;
    }

    /** Execute the queued import. The examination connection must already be configured. */
    public function process(int $batchId): RegistrationImportBatch
    {
        $batch = RegistrationImportBatch::query()->findOrFail($batchId);
        $path = Storage::disk('local')->path($batch->stored_name);

        if (! is_file($path)) {
            throw new RuntimeException('The uploaded registration spreadsheet is missing.');
        }

        if (! class_exists(XlsxReader::class)) {
            throw new RuntimeException('OpenSpout is required. Run: composer require openspout/openspout:^4.24');
        }

        $batch->update([
            'status' => 'processing',
            'started_at' => $batch->started_at ?? now(),
            'heartbeat_at' => now(),
            'failure_message' => null,
        ]);

        $reader = null;

        try {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $reader = match ($extension) {
                'xlsx' => new XlsxReader(),
                'csv' => new CsvReader(),
                default => throw new RuntimeException('Enterprise import supports XLSX and CSV files only.'),
            };

            $totalRows = $this->quickTotalRows($path, $extension);
            $chunkSize = max(500, (int) $batch->chunk_size);
            $totalChunks = $totalRows > 0 ? (int) ceil($totalRows / $chunkSize) : 0;

            $batch->update([
                'total_rows' => $totalRows,
                'total_chunks' => $totalChunks,
            ]);

            $masters = $this->maps->load();
            $totals = ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'failed' => 0, 'warning' => 0, 'conflict' => 0];
            $seenRegs = [];
            $seenUserIds = [];
            $prepared = [];
            $headers = null;
            $sourceRow = 0;
            $chunk = 0;

            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $spreadsheetRow) {
                    $sourceRow++;
                    $values = $spreadsheetRow->toArray();

                    if ($sourceRow === 1) {
                        $headers = $this->validateHeaders($values);
                        continue;
                    }

                    if ($this->isEmptyRow($values)) {
                        continue;
                    }

                    if ($headers === null) {
                        throw new RuntimeException('The spreadsheet header row is missing.');
                    }

                    $columnCount = count($headers);
                    $raw = array_combine(
                        $headers,
                        array_slice(array_pad($values, $columnCount, null), 0, $columnCount),
                    );

                    $normalized = $this->normalizer->normalize($raw, $batch->id);
                    $data = $normalized['attributes'];
                    $data['division_code'] = $data['district_code'] === null
                        ? null
                        : ($masters['district_division'][(string) $data['district_code']] ?? null);

                    $universityResult = $this->universityPolicy->apply($data, $masters['university']);
                    $data = $universityResult['attributes'];
                    $warnings = array_values(array_unique([
                        ...$normalized['warnings'],
                        ...$universityResult['warnings'],
                    ]));
                    $errors = $this->validator->validate($data, $masters);

                    $reg = $data['reg'] ?: null;
                    $userId = $data['user_id'] ?: null;
                    if (($reg !== null && isset($seenRegs[$reg])) || ($userId !== null && isset($seenUserIds[$userId]))) {
                        $errors[] = 'Duplicate REG or USER appears more than once in the same spreadsheet.';
                    }
                    if ($reg !== null) {
                        $seenRegs[$reg] = true;
                    }
                    if ($userId !== null) {
                        $seenUserIds[$userId] = true;
                    }

                    $prepared[] = compact('sourceRow', 'data', 'warnings', 'errors');

                    if (count($prepared) >= $chunkSize) {
                        $chunk++;
                        $this->flushChunk($batch, $prepared, $totals, $sourceRow, $chunk, $totalRows, $totalChunks);
                        $prepared = [];
                    }
                }

                // Registration templates are single-sheet files. Ignore any extra sheets.
                break;
            }

            if ($prepared !== []) {
                $chunk++;
                $this->flushChunk($batch, $prepared, $totals, $sourceRow, $chunk, $totalRows, $totalChunks);
            }

            $reader->close();
            $reader = null;

            $actualRows = $totals['processed'];
            $batch->update([
                'status' => $totals['failed'] > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $totalRows > 0 ? $totalRows : $actualRows,
                'processed_rows' => $actualRows,
                'total_chunks' => $totalChunks > 0 ? $totalChunks : $chunk,
                'current_chunk' => $chunk,
                'progress_percent' => 100,
                'inserted_rows' => $totals['inserted'],
                'updated_rows' => $totals['updated'],
                'failed_rows' => $totals['failed'],
                'warning_rows' => $totals['warning'],
                'identity_conflict_rows' => $totals['conflict'],
                'finished_at' => now(),
                'heartbeat_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            if ($reader !== null) {
                try {
                    $reader->close();
                } catch (Throwable) {
                    // Preserve the original exception.
                }
            }

            $batch->update([
                'status' => 'failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                'finished_at' => now(),
                'heartbeat_at' => now(),
            ]);

            throw $exception;
        }
    }

    /** @param list<mixed> $values @return list<string> */
    private function validateHeaders(array $values): array
    {
        $expected = config('registrations.headers');
        $headers = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            array_slice(array_pad($values, count($expected), null), 0, count($expected)),
        );

        if ($headers !== $expected) {
            throw new RuntimeException('Headers do not match the downloaded registration template.');
        }

        return $headers;
    }

    /** @param array<int,mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param list<array{sourceRow:int,data:array<string,mixed>,warnings:list<string>,errors:list<string>}> $prepared
     * @param array{processed:int,inserted:int,updated:int,failed:int,warning:int,conflict:int} $totals
     */
    private function flushChunk(
        RegistrationImportBatch $batch,
        array $prepared,
        array &$totals,
        int $sourceRow,
        int $chunk,
        int $totalRows,
        int $totalChunks,
    ): void {
        $chunkTotals = $this->persistChunkBulk($batch->id, $prepared);
        foreach ($chunkTotals as $key => $value) {
            $totals[$key] += $value;
        }

        $processed = $totals['processed'];
        $batch->update([
            'processed_rows' => $processed,
            'current_row' => $sourceRow,
            'current_chunk' => $chunk,
            'progress_percent' => $totalRows > 0 ? min(100, round(($processed / $totalRows) * 100, 4)) : 0,
            'inserted_rows' => $totals['inserted'],
            'updated_rows' => $totals['updated'],
            'failed_rows' => $totals['failed'],
            'warning_rows' => $totals['warning'],
            'identity_conflict_rows' => $totals['conflict'],
            'total_chunks' => $totalChunks,
            'heartbeat_at' => now(),
        ]);

        gc_collect_cycles();
    }

    /**
     * Persist a complete chunk with a constant number of SQL statements.
     *
     * @param list<array{sourceRow:int,data:array<string,mixed>,warnings:list<string>,errors:list<string>}> $rows
     * @return array{processed:int,inserted:int,updated:int,failed:int,warning:int,conflict:int}
     */
    private function persistChunkBulk(int $batchId, array $rows): array
    {
        $totals = ['processed' => count($rows), 'inserted' => 0, 'updated' => 0, 'failed' => 0, 'warning' => 0, 'conflict' => 0];
        if ($rows === []) {
            return $totals;
        }

        $candidateRows = array_values(array_filter($rows, static fn (array $row): bool => $row['errors'] === []));
        $regs = array_values(array_unique(array_filter(array_map(static fn (array $row): ?string => $row['data']['reg'] ?: null, $candidateRows))));
        $userIds = array_values(array_unique(array_filter(array_map(static fn (array $row): ?string => $row['data']['user_id'] ?: null, $candidateRows))));

        $existing = collect();
        if ($regs !== [] || $userIds !== []) {
            $existing = DB::connection('exam')->table('registrations')
                ->where(function ($query) use ($regs, $userIds): void {
                    if ($regs !== []) {
                        $query->whereIn('reg', $regs);
                    }
                    if ($userIds !== []) {
                        $query->{$regs === [] ? 'whereIn' : 'orWhereIn'}('user_id', $userIds);
                    }
                })
                ->get()
                ->map(static fn (object $row): array => (array) $row);
        }

        $byReg = $existing->keyBy('reg');
        $byUser = $existing->keyBy('user_id');
        $accepted = [];
        $metadata = [];
        $auditRows = [];
        $timestamp = now();

        foreach ($rows as $row) {
            $data = $row['data'];
            $errors = $row['errors'];
            $regMatch = $data['reg'] ? $byReg->get($data['reg']) : null;
            $userMatch = $data['user_id'] ? $byUser->get($data['user_id']) : null;

            $auditBase = [
                'batch_id' => $batchId,
                'source_row' => $row['sourceRow'],
                'registration_id' => null,
                'reg' => $data['reg'] ?: null,
                'user_id' => $data['user_id'] ?: null,
                'warnings' => $row['warnings'] === [] ? null : json_encode($row['warnings'], JSON_UNESCAPED_UNICODE),
                'errors' => null,
                'before_data' => null,
                'after_data' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if ($errors !== []) {
                $auditBase['action'] = 'rejected';
                $auditBase['errors'] = json_encode(array_values(array_unique($errors)), JSON_UNESCAPED_UNICODE);
                $auditRows[] = $auditBase;
                $totals['failed']++;
                continue;
            }

            if (($regMatch && $regMatch['user_id'] !== $data['user_id'])
                || ($userMatch && $userMatch['reg'] !== $data['reg'])
                || ($regMatch && $userMatch && $regMatch['id'] !== $userMatch['id'])) {
                $auditBase['action'] = 'identity_conflict';
                $auditBase['errors'] = json_encode(['REG and USER identify different candidates. Correct the spreadsheet and import again.'], JSON_UNESCAPED_UNICODE);
                $auditRows[] = $auditBase;
                $totals['failed']++;
                $totals['conflict']++;
                continue;
            }

            $isUpdate = $regMatch !== null;
            // Keep a uniform column set for bulk upsert. created_at is excluded
            // from updateColumns, so existing registrations retain their value.
            $accepted[] = $data;
            $metadata[$data['reg']] = [
                'base' => $auditBase,
                'action' => $isUpdate ? 'updated' : 'inserted',
                'before' => $isUpdate ? $regMatch : null,
                'warnings' => $row['warnings'],
            ];
        }

        DB::connection('exam')->transaction(function () use ($accepted, $regs, $metadata, &$auditRows, &$totals): void {
            if ($accepted !== []) {
                $updateColumns = [
                    'national_id', 'name', 'father_name', 'mother_name', 'name_bn', 'father_name_bn',
                    'mother_name_bn', 'birth_date', 'sex_code', 'district_code', 'division_code',
                    'university_code', 'bachelor_subject_code', 'post_related_subject_code',
                    'cadre_category', 'has_ff_quota', 'has_em_quota', 'has_phc_quota', 'has_quota',
                    'status', 'validation_status', 'comment', 'source_batch_id', 'updated_at',
                ];

                DB::connection('exam')->table('registrations')->upsert($accepted, ['reg'], $updateColumns);

                $afterByReg = DB::connection('exam')->table('registrations')
                    ->whereIn('reg', array_keys($metadata))
                    ->get()
                    ->map(static fn (object $row): array => (array) $row)
                    ->keyBy('reg');

                foreach ($metadata as $reg => $meta) {
                    $after = $afterByReg->get($reg);
                    if ($after === null) {
                        throw new RuntimeException("Registration {$reg} was not found after bulk upsert.");
                    }

                    $audit = $meta['base'];
                    $audit['registration_id'] = $after['id'];
                    $audit['action'] = $meta['action'];
                    // Rollback needs the old snapshot only for updates. Insert rollback uses registration_id.
                    $audit['before_data'] = $meta['before'] === null ? null : json_encode($meta['before'], JSON_UNESCAPED_UNICODE);
                    $audit['after_data'] = null;
                    $auditRows[] = $audit;

                    $totals[$meta['action'] === 'updated' ? 'updated' : 'inserted']++;
                    if ($meta['warnings'] !== []) {
                        $totals['warning']++;
                    }
                }
            }

            foreach (array_chunk($auditRows, 1000) as $auditChunk) {
                DB::connection('exam')->table('registration_import_rows')->insert($auditChunk);
            }
        });

        return $totals;
    }

    /** Obtain XLSX row count from worksheet dimension without loading the workbook. */
    private function quickTotalRows(string $path, string $extension): int
    {
        if ($extension === 'csv') {
            return 0;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return 0;
        }

        $stream = $zip->getStream('xl/worksheets/sheet1.xml');
        if ($stream === false) {
            $zip->close();
            return 0;
        }

        $prefix = fread($stream, 131072) ?: '';
        fclose($stream);
        $zip->close();

        if (preg_match('/<dimension[^>]+ref="(?:[A-Z]+\d+:)?[A-Z]+(\d+)"/i', $prefix, $match) !== 1) {
            return 0;
        }

        return max(0, ((int) $match[1]) - 1);
    }
}
