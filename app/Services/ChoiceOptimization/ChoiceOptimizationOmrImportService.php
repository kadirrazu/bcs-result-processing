<?php

namespace App\Services\ChoiceOptimization;

use App\Jobs\ProcessChoiceOptimizationOmrImport;
use App\Models\ChoiceOptimizationOmrBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;

final class ChoiceOptimizationOmrImportService
{
    public function enqueue(UploadedFile $file, int $actorId, int $examinationId): ChoiceOptimizationOmrBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = sprintf('choice-optimization/omr/%s-%s.%s', now()->format('YmdHis'), bin2hex(random_bytes(8)), $extension);
        $file->storeAs(dirname($storedName), basename($storedName), 'local');

        $batch = ChoiceOptimizationOmrBatch::query()->create([
            'examination_id' => $examinationId,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'status' => 'queued',
            'configured_maximum_choices' => max(1, (int) config('choice-optimization.omr_max_choices', 20)),
            'queued_at' => now(),
            'created_by' => $actorId,
        ]);

        ProcessChoiceOptimizationOmrImport::dispatch($examinationId, (int) $batch->id, $actorId);

        return $batch;
    }

    public function process(int $batchId): ChoiceOptimizationOmrBatch
    {
        $batch = ChoiceOptimizationOmrBatch::query()->findOrFail($batchId);
        $path = Storage::disk('local')->path($batch->stored_name);
        if (! is_file($path)) {
            throw new RuntimeException('The uploaded Viva OMR spreadsheet is missing.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = $extension === 'csv' ? new CsvReader() : new XlsxReader();
        DB::connection('exam')->table('choice_optimization_omr_staging')->where('batch_id', $batchId)->delete();

        $batch->update([
            'status' => 'processing', 'started_at' => now(), 'processed_rows' => 0,
            'valid_rows' => 0, 'invalid_rows' => 0, 'conflict_rows' => 0,
            'progress_percent' => 0, 'failure_message' => null,
        ]);

        try {
            $reader->open($path);
            $headers = null;
            $buffer = [];
            $staged = 0;
            $sourceRow = 0;
            $chunkSize = max(100, (int) config('choice-optimization.import_chunk_size', 1000));
            $required = ['reg', 'change_choice'];
            $max = (int) $batch->configured_maximum_choices;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $sourceRow++;
                    $values = $row->toArray();
                    if ($headers === null) {
                        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), $values);
                        foreach ($required as $column) {
                            if (! in_array($column, $headers, true)) {
                                throw new RuntimeException("Required OMR column [{$column}] is missing.");
                            }
                        }
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

                    $choices = [];
                    for ($i = 1; $i <= $max; $i++) {
                        $column = 'opt_'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                        $choices[$column] = $this->normalizeIdentity($payload[$column] ?? null);
                    }
                    $choiceCount = count(array_filter($choices, static fn ($value) => $value !== null));
                    $reg = $this->normalizeIdentity($payload['reg'] ?? null);
                    $decision = strtoupper(trim((string) ($payload['change_choice'] ?? '')));

                    $buffer[] = [
                        'batch_id' => $batchId,
                        'source_row' => $sourceRow,
                        'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'raw_reg' => $reg,
                        'effective_reg' => $reg,
                        'change_choice' => $decision !== '' ? $decision : null,
                        'raw_choices' => json_encode($choices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'raw_choice_count' => $choiceCount,
                        'validation_status' => 'pending',
                        'resolution_status' => 'unresolved',
                        'created_at' => now(), 'updated_at' => now(),
                    ];

                    if (count($buffer) >= $chunkSize) {
                        DB::connection('exam')->table('choice_optimization_omr_staging')->insert($buffer);
                        $staged += count($buffer);
                        $buffer = [];
                        $batch->update(['processed_rows' => $staged]);
                    }
                }
                break;
            }

            if ($buffer !== []) {
                DB::connection('exam')->table('choice_optimization_omr_staging')->insert($buffer);
                $staged += count($buffer);
            }
            $reader->close();

            return tap($batch)->update([
                'status' => 'staged', 'total_rows' => $staged, 'processed_rows' => $staged,
                'progress_percent' => 100, 'finished_at' => now(),
            ])->refresh();
        } catch (Throwable $e) {
            try { $reader->close(); } catch (Throwable) {}
            $batch->update(['status' => 'failed', 'failure_message' => mb_substr($e->getMessage(), 0, 65000), 'finished_at' => now()]);
            throw $e;
        }
    }

    private function emptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) ($value ?? '')) !== '') return false;
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
