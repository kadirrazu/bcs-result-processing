<?php

namespace App\Services\ChoiceValidation;

use App\Jobs\ProcessChoiceSourceImport;
use App\Models\ChoiceValidationImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;
use ZipArchive;

final class ChoiceSourceImportService
{
    public function __construct(private readonly ChoiceColumnResolver $columns) {}

    public function enqueue(UploadedFile $file, int $actorId, int $examinationId): ChoiceValidationImportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = sprintf('choice-validation-imports/%s-%s.%s', now()->format('YmdHis'), bin2hex(random_bytes(8)), $extension);
        $file->storeAs(dirname($storedName), basename($storedName), 'local');

        $batch = ChoiceValidationImportBatch::query()->create([
            'examination_id' => $examinationId,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'status' => 'queued',
            'configured_maximum_choices' => $this->columns->maximumAllowedChoices(),
            'queued_at' => now(),
            'created_by' => $actorId,
        ]);

        ProcessChoiceSourceImport::dispatch($examinationId, (int) $batch->id, $actorId);
        return $batch;
    }

    /**
     * Stage raw spreadsheet rows only. Candidate/source business validation is a separate step.
     */
    public function process(int $batchId): ChoiceValidationImportBatch
    {
        $batch = ChoiceValidationImportBatch::query()->findOrFail($batchId);
        $path = Storage::disk('local')->path($batch->stored_name);
        if (! is_file($path)) {
            throw new RuntimeException('The uploaded Choice spreadsheet is missing.');
        }

        DB::connection('exam')->table('choice_validation_import_staging')->where('batch_id', $batchId)->delete();
        $batch->update([
            'status' => 'processing',
            'started_at' => $batch->started_at ?? now(),
            'failure_message' => null,
            'total_rows' => 0,
            'processed_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'progress_percent' => 0,
            'validated_at' => null,
            'finished_at' => null,
        ]);

        $reader = null;
        try {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $reader = match ($extension) {
                'xlsx' => new XlsxReader(),
                'csv' => new CsvReader(),
                default => throw new RuntimeException('Choice import supports XLSX and CSV files only.'),
            };

            // Fast XLSX worksheet-dimension lookup: gives the UI a useful denominator
            // before OpenSpout starts streaming the actual rows.
            $estimatedTotalRows = $this->quickTotalRows($path, $extension);
            if ($estimatedTotalRows > 0) {
                $batch->update(['total_rows' => $estimatedTotalRows]);
            }

            $reader->open($path);
            $headers = null;
            $sourceRow = 0;
            $buffer = [];
            $staged = 0;
            $chunkSize = max(100, (int) config('choice-validation.staging_chunk_size', 1000));
            $timestamp = now()->format('Y-m-d H:i:s');

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $spreadsheetRow) {
                    $sourceRow++;
                    $values = $spreadsheetRow->toArray();

                    if ($sourceRow === 1) {
                        // Header validation remains an import-level blocking contract.
                        $headers = $this->columns->validateHeaders($values, (int) $batch->configured_maximum_choices);
                        continue;
                    }

                    if ($headers === null) {
                        throw new RuntimeException('Choice spreadsheet header row is missing.');
                    }

                    if ($this->isEmptyRow($values, count($headers))) {
                        continue;
                    }

                    $raw = array_combine(
                        $headers,
                        array_slice(array_pad($values, count($headers), null), 0, count($headers)),
                    );

                    $payload = [];
                    foreach ($headers as $header) {
                        $payload[$header] = $this->rawText($raw[$header] ?? null);
                    }

                    $choiceMap = [];
                    $choiceCount = 0;
                    foreach ($this->columns->choiceColumns((int) $batch->configured_maximum_choices) as $column) {
                        $value = $this->rawText($raw[$column] ?? null);
                        $choiceMap[$column] = $value;
                        if ($value !== null) {
                            $choiceCount++;
                        }
                    }

                    $buffer[] = [
                        'batch_id' => $batchId,
                        'source_row' => $sourceRow,
                        'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'registration_id' => null,
                        'user_id' => $this->normalizeIdentity($raw['user'] ?? null),
                        'reg' => $this->normalizeIdentity($raw['reg'] ?? null),
                        'raw_choices' => json_encode($choiceMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'raw_choice_count' => $choiceCount,
                        'validation_status' => 'pending',
                        'validation_errors' => null,
                        'validation_warnings' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    if (count($buffer) >= $chunkSize) {
                        DB::connection('exam')->table('choice_validation_import_staging')->insert($buffer);
                        $staged += count($buffer);
                        $buffer = [];
                        $this->updateProgress($batch, $staged, $estimatedTotalRows);
                    }
                }
                break;
            }

            if ($buffer !== []) {
                DB::connection('exam')->table('choice_validation_import_staging')->insert($buffer);
                $staged += count($buffer);
                $this->updateProgress($batch, $staged, $estimatedTotalRows);
            }

            $reader->close();
            $reader = null;

            $batch->update([
                'status' => 'staged',
                'total_rows' => $staged,
                'processed_rows' => $staged,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'progress_percent' => 100,
                'finished_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $e) {
            if ($reader !== null) {
                try { $reader->close(); } catch (Throwable) {}
            }

            $batch->update([
                'status' => 'failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }

    private function updateProgress(ChoiceValidationImportBatch $batch, int $staged, int $totalRows): void
    {
        $batch->update([
            'processed_rows' => $staged,
            'progress_percent' => $totalRows > 0
                ? min(99.9, round(($staged / $totalRows) * 100, 4))
                : 0,
        ]);
    }

    /**
     * Read the XLSX worksheet dimension without parsing the workbook twice.
     * Returns data-row count (header excluded). CSV remains unknown until streamed.
     */
    private function quickTotalRows(string $path, string $extension): int
    {
        if ($extension !== 'xlsx') {
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

    private function isEmptyRow(array $values, int $columns): bool
    {
        foreach (array_slice(array_pad($values, $columns, null), 0, $columns) as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }
        return true;
    }

    private function rawText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function normalizeIdentity(mixed $value): ?string
    {
        $value = $this->rawText($value);
        if ($value !== null && preg_match('/^\d+\.0$/', $value) === 1) {
            $value = substr($value, 0, -2);
        }
        return $value;
    }
}
