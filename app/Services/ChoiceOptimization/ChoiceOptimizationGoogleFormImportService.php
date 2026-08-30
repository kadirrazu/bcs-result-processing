<?php

namespace App\Services\ChoiceOptimization;

use App\Jobs\ProcessChoiceOptimizationGoogleFormImport;
use App\Models\ChoiceOptimizationGoogleFormBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;

final class ChoiceOptimizationGoogleFormImportService
{
    public function enqueue(UploadedFile $file, int $actorId, int $examinationId): ChoiceOptimizationGoogleFormBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = sprintf('choice-optimization/google-form/%s-%s.%s', now()->format('YmdHis'), bin2hex(random_bytes(8)), $extension);
        $file->storeAs(dirname($storedName), basename($storedName), 'local');

        $batch = ChoiceOptimizationGoogleFormBatch::query()->create([
            'examination_id' => $examinationId,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'status' => 'queued',
            'queued_at' => now(),
            'created_by' => $actorId,
        ]);

        ProcessChoiceOptimizationGoogleFormImport::dispatch($examinationId, (int) $batch->id, $actorId);

        return $batch;
    }

    public function process(int $batchId): ChoiceOptimizationGoogleFormBatch
    {
        $batch = ChoiceOptimizationGoogleFormBatch::query()->findOrFail($batchId);
        $path = Storage::disk('local')->path($batch->stored_name);
        if (! is_file($path)) {
            throw new RuntimeException('The uploaded Google Form spreadsheet is missing.');
        }

        DB::connection('exam')->table('choice_optimization_google_form_rows')->where('batch_id', $batchId)->delete();

        $batch->update([
            'status' => 'processing',
            'started_at' => now(),
            'processed_rows' => 0,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'merged_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'finished_at' => null,
        ]);

        try {
            // First pass: validate the header and determine the exact number of data rows.
            // This makes staging progress measurable instead of an indeterminate spinner.
            $totalRows = $this->inspectAndCountRows($path);
            $batch->update(['total_rows' => $totalRows]);

            $reader = $this->readerFor($path);
            $reader->open($path);
            $headers = null;
            $buffer = [];
            $staged = 0;
            $sourceRow = 0;
            $chunkSize = max(100, (int) config('choice-optimization.import_chunk_size', 1000));

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $sourceRow++;
                    $values = $row->toArray();

                    if ($headers === null) {
                        $headers = $this->normalizedHeaders($values);
                        continue;
                    }

                    if ($this->emptyRow($values)) {
                        continue;
                    }

                    $payload = [];
                    foreach ($headers as $index => $header) {
                        if ($header !== '') {
                            $payload[$header] = $this->rawText($values[$index] ?? null);
                        }
                    }

                    $buffer[] = [
                        'batch_id' => $batchId,
                        'source_row' => $sourceRow,
                        'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'raw_reg' => $this->normalizeNumericText($payload['reg'] ?? null),
                        'raw_bcs' => $this->normalizeNumericText($payload['bcs'] ?? null),
                        'raw_cadre' => $this->normalizeCadre($payload['cadre'] ?? null),
                        'validation_status' => 'pending',
                        'merge_status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($buffer) >= $chunkSize) {
                        DB::connection('exam')->table('choice_optimization_google_form_rows')->insert($buffer);
                        $staged += count($buffer);
                        $buffer = [];
                        $this->writeStageProgress($batch, $staged, $totalRows);
                    }
                }
                break;
            }

            if ($buffer !== []) {
                DB::connection('exam')->table('choice_optimization_google_form_rows')->insert($buffer);
                $staged += count($buffer);
                $this->writeStageProgress($batch, $staged, $totalRows);
            }

            $reader->close();

            return tap($batch)->update([
                'status' => 'staged',
                'total_rows' => $staged,
                'processed_rows' => $staged,
                'progress_percent' => 100,
                'finished_at' => now(),
            ])->refresh();
        } catch (Throwable $e) {
            if (isset($reader)) {
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

    private function inspectAndCountRows(string $path): int
    {
        $reader = $this->readerFor($path);
        $reader->open($path);

        try {
            $headers = null;
            $count = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();
                    if ($headers === null) {
                        $headers = $this->normalizedHeaders($values);
                        $this->assertRequiredHeaders($headers);
                        continue;
                    }
                    if (! $this->emptyRow($values)) {
                        $count++;
                    }
                }
                break;
            }

            if ($headers === null) {
                throw new RuntimeException('The Google Form spreadsheet is empty.');
            }

            return $count;
        } finally {
            $reader->close();
        }
    }

    private function normalizedHeaders(array $values): array
    {
        return array_map(static fn ($v): string => strtolower(trim((string) $v)), $values);
    }

    private function assertRequiredHeaders(array $headers): void
    {
        foreach (['reg', 'bcs', 'cadre'] as $column) {
            if (! in_array($column, $headers, true)) {
                throw new RuntimeException("Required Google Form column [{$column}] is missing.");
            }
        }
    }

    private function readerFor(string $path): CsvReader|XlsxReader
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv' ? new CsvReader() : new XlsxReader();
    }

    private function writeStageProgress(ChoiceOptimizationGoogleFormBatch $batch, int $processed, int $total): void
    {
        $batch->update([
            'processed_rows' => $processed,
            'progress_percent' => $total > 0 ? round(min(100, ($processed / $total) * 100), 2) : 100,
        ]);
    }

    private function emptyRow(array $values): bool
    {
        foreach ($values as $value) {
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

    private function normalizeNumericText(mixed $value): ?string
    {
        $value = $this->rawText($value);
        if ($value !== null && preg_match('/^\d+\.0$/', $value) === 1) {
            return substr($value, 0, -2);
        }
        return $value;
    }

    private function normalizeCadre(mixed $value): ?string
    {
        $value = $this->rawText($value);
        return $value === null ? null : strtoupper($value);
    }
}
