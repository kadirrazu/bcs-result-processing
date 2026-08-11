<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceValidationImportBatch;
use App\Models\ChoiceValidationProcessingAudit;
use App\Models\ImportCorrectionEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

final class ChoiceInvalidRowCorrectionService
{
    public function __construct(private readonly ChoiceColumnResolver $columns) {}

    /** @return list<string> */
    public function correctionHeaders(ChoiceValidationImportBatch $batch): array
    {
        return [
            'source_batch_id',
            'source_row',
            'user',
            'reg',
            ...$this->columns->choiceColumns((int) $batch->configured_maximum_choices),
            'validation_error',
        ];
    }

    public function createWorkbook(ChoiceValidationImportBatch $batch, string $path): int
    {
        $rows = $batch->stagingRows()
            ->where('validation_status', 'invalid')
            ->orderBy('source_row')
            ->get();

        $headers = $this->correctionHeaders($batch);
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $payload = is_array($row->raw_payload)
                ? $row->raw_payload
                : (json_decode((string) $row->raw_payload, true) ?: []);
            $errors = is_array($row->validation_errors)
                ? $row->validation_errors
                : (json_decode((string) $row->validation_errors, true) ?: []);

            $values = [(string) $batch->id, (string) $row->source_row];
            foreach (['user', 'reg', ...$this->columns->choiceColumns((int) $batch->configured_maximum_choices)] as $header) {
                $values[] = $payload[$header] ?? null;
            }
            $values[] = implode(' | ', array_map('strval', $errors));
            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();
        return $rows->count();
    }

    /** @return array{corrected_rows:int,source_rows:list<int>} */
    public function apply(ChoiceValidationImportBatch $batch, UploadedFile $file, User $actor): array
    {
        if (! in_array((string) $batch->status, ['validated', 'partially_approved'], true)) {
            throw ValidationException::withMessages([
                'correction_file' => 'Invalid-row correction is available only after source validation has completed.',
            ]);
        }

        if ((int) $batch->invalid_rows < 1) {
            throw ValidationException::withMessages([
                'correction_file' => 'This batch has no invalid rows pending correction.',
            ]);
        }

        $entries = $this->readCorrectionFile($batch, $file);
        if ($entries === []) {
            throw ValidationException::withMessages([
                'correction_file' => 'The correction file does not contain any data rows.',
            ]);
        }

        $sourceRows = array_column($entries, 'source_row');
        if (count($sourceRows) !== count(array_unique($sourceRows))) {
            throw ValidationException::withMessages([
                'correction_file' => 'The correction file contains the same source_row more than once.',
            ]);
        }

        $currentRows = $batch->stagingRows()
            ->whereIn('source_row', $sourceRows)
            ->get()
            ->keyBy('source_row');

        $problems = [];
        foreach ($entries as $entry) {
            $sourceRow = $entry['source_row'];
            $current = $currentRows->get($sourceRow);
            if ($current === null) {
                $problems[] = "Source row {$sourceRow} does not belong to Choice batch #{$batch->id}.";
                continue;
            }
            if ((string) $current->validation_status->value !== 'invalid') {
                $problems[] = "Source row {$sourceRow} is no longer invalid and cannot be changed through the correction upload.";
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['correction_file' => array_slice($problems, 0, 10)]);
        }

        $timestamp = now();
        DB::connection('exam')->transaction(function () use ($batch, $entries, $currentRows, $actor, $file, $timestamp): void {
            foreach ($entries as $entry) {
                $sourceRow = $entry['source_row'];
                $current = $currentRows->get($sourceRow);
                $correctedPayload = $entry['payload'];
                $originalPayload = is_array($current->raw_payload)
                    ? $current->raw_payload
                    : (json_decode((string) $current->raw_payload, true) ?: []);

                ImportCorrectionEntry::query()->create([
                    'module' => 'choice_validation',
                    'batch_id' => (int) $batch->id,
                    'staging_row_id' => (int) $current->id,
                    'source_row' => (int) $sourceRow,
                    'validation_status_before' => 'invalid',
                    'original_payload' => $originalPayload,
                    'corrected_payload' => $correctedPayload,
                    'source_filename' => $file->getClientOriginalName(),
                    'actor_id' => (int) $actor->id,
                    'actor_name' => (string) $actor->name,
                    'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                    'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
                    'created_at' => $timestamp,
                ]);

                $choiceMap = [];
                $choiceCount = 0;
                foreach ($this->columns->choiceColumns((int) $batch->configured_maximum_choices) as $column) {
                    $value = $this->sourceText($correctedPayload[$column] ?? null);
                    $choiceMap[$column] = $value;
                    if ($value !== null) {
                        $choiceCount++;
                    }
                }

                $current->update([
                    'raw_payload' => $correctedPayload,
                    'registration_id' => null,
                    'user_id' => $this->normalizeIdentity($correctedPayload['user'] ?? null),
                    'reg' => $this->normalizeIdentity($correctedPayload['reg'] ?? null),
                    'raw_choices' => $choiceMap,
                    'raw_choice_count' => $choiceCount,
                    'validation_status' => 'pending',
                    'validation_errors' => null,
                    'validation_warnings' => null,
                ]);
            }

            ChoiceValidationProcessingAudit::query()->create([
                'action' => 'CHOICE_SOURCE_INVALID_ROWS_CORRECTED',
                'actor_id' => (int) $actor->id,
                'actor_name' => (string) $actor->name,
                'reason' => 'Corrected invalid Choice source rows and queued them for revalidation.',
                'summary' => [
                    'batch_id' => (int) $batch->id,
                    'corrected_rows' => count($entries),
                    'source_rows' => $sourceRows = array_column($entries, 'source_row'),
                    'source_filename' => $file->getClientOriginalName(),
                ],
                'batch_id' => (int) $batch->id,
                'created_at' => $timestamp,
            ]);
        });

        Log::channel('single')->info('Choice source invalid rows corrected.', [
            'batch_id' => (int) $batch->id,
            'corrected_rows' => count($entries),
            'source_rows' => $sourceRows,
            'actor_id' => (int) $actor->id,
            'source_filename' => $file->getClientOriginalName(),
        ]);

        return ['corrected_rows' => count($entries), 'source_rows' => array_values($sourceRows)];
    }

    /** @return list<array{source_row:int,payload:array<string,mixed>}> */
    private function readCorrectionFile(ChoiceValidationImportBatch $batch, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $reader = match ($extension) {
            'xlsx' => new XlsxReader(),
            'csv' => new CsvReader(),
            default => throw ValidationException::withMessages(['correction_file' => 'Use an XLSX or CSV correction file.']),
        };

        $expected = $this->correctionHeaders($batch);
        $payloadHeaders = ['user', 'reg', ...$this->columns->choiceColumns((int) $batch->configured_maximum_choices)];
        $entries = [];
        $rowNumber = 0;
        $reader->open($file->getRealPath());

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
                        if ($actual !== $expected || count(array_filter(array_slice($values, count($expected)), static fn ($v) => trim((string) ($v ?? '')) !== '')) > 0) {
                            throw ValidationException::withMessages([
                                'correction_file' => 'The correction workbook headers were changed. Download a fresh Invalid Rows workbook from this batch.',
                            ]);
                        }
                        continue;
                    }

                    if ($this->isEmptyRow($values, count($expected))) {
                        continue;
                    }

                    $values = array_slice(array_pad($values, count($expected), null), 0, count($expected));
                    $mapped = array_combine($expected, $values);
                    $batchText = trim((string) ($mapped['source_batch_id'] ?? ''));
                    $sourceRowText = trim((string) ($mapped['source_row'] ?? ''));
                    if ($batchText !== (string) $batch->id) {
                        throw ValidationException::withMessages([
                            'correction_file' => "Correction file row {$rowNumber} targets a different source batch.",
                        ]);
                    }
                    if (preg_match('/^\d+$/', $sourceRowText) !== 1 || (int) $sourceRowText < 2) {
                        throw ValidationException::withMessages([
                            'correction_file' => "Correction file row {$rowNumber} has an invalid source_row value.",
                        ]);
                    }

                    $payload = [];
                    foreach ($payloadHeaders as $header) {
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

    private function isEmptyRow(array $values, int $columns): bool
    {
        foreach (array_slice(array_pad($values, $columns, null), 0, $columns) as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }
        return true;
    }

    private function sourceText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function normalizeIdentity(mixed $value): ?string
    {
        $value = $this->sourceText($value);
        if ($value !== null && preg_match('/^\d+\.0$/', $value) === 1) {
            $value = substr($value, 0, -2);
        }
        return $value;
    }
}
