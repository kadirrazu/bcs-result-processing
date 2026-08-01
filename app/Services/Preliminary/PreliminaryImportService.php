<?php

namespace App\Services\Preliminary;

use App\Jobs\ProcessPreliminaryImport;
use App\Models\PreliminaryImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;
use ZipArchive;

/** Streams a four-column preliminary source file into the staging table. */
final class PreliminaryImportService
{
    public function enqueue(UploadedFile $file, int $userId, int $examinationId): PreliminaryImportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = sprintf(
            'preliminary-imports/%s-%s.%s',
            now()->format('YmdHis'),
            bin2hex(random_bytes(8)),
            $extension,
        );

        $file->storeAs(dirname($storedName), basename($storedName), 'local');

        $batch = PreliminaryImportBatch::query()->create([
            'examination_id' => $examinationId,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'status' => 'queued',
            'queued_at' => now(),
            'created_by' => $userId,
        ]);

        ProcessPreliminaryImport::dispatch($examinationId, $batch->id, $userId);

        return $batch;
    }

    public function process(int $batchId): PreliminaryImportBatch
    {
        $batch = PreliminaryImportBatch::query()->findOrFail($batchId);
        $path = Storage::disk('local')->path($batch->stored_name);

        if (! is_file($path)) {
            throw new RuntimeException('The uploaded preliminary spreadsheet is missing.');
        }

        $batch->update([
            'status' => 'staging',
            'started_at' => $batch->started_at ?? now(),
            'failure_message' => null,
            'processed_rows' => 0,
            'staged_rows' => 0,
            'progress_percent' => 0,
        ]);

        $reader = null;

        try {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $reader = match ($extension) {
                'xlsx' => new XlsxReader(),
                'csv' => new CsvReader(),
                default => throw new RuntimeException('Preliminary import supports XLSX and CSV files only.'),
            };

            $totalRows = $this->quickTotalRows($path, $extension);
            if ($totalRows > 0) {
                $batch->update(['total_rows' => $totalRows]);
            }

            $reader->open($path);
            $headers = null;
            $buffer = [];
            $sourceRow = 0;
            $staged = 0;
            $chunkSize = max(500, (int) config('preliminary.staging_chunk_size', 4000));
            $timestamp = now()->format('Y-m-d H:i:s');

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

                    $raw = array_combine(
                        $headers,
                        array_slice(array_pad($values, count($headers), null), 0, count($headers)),
                    );

                    $buffer[] = [
                        'batch_id' => $batchId,
                        'source_row' => $sourceRow,
                        'raw_user' => $this->nullable($raw['user'] ?? null),
                        'raw_reg' => $this->nullable($raw['reg'] ?? null),
                        'raw_mark' => $this->rawText($raw['mark'] ?? null),
                        'raw_candidate_status' => $this->nullable($raw['candidate_status'] ?? null),
                        'registration_id' => null,
                        'user_id' => $this->normalizeUser($raw['user'] ?? null),
                        'reg' => $this->normalizeReg($raw['reg'] ?? null),
                        'mark' => null,
                        'candidate_status' => null,
                        'validation_status' => 'pending',
                        'validation_errors' => null,
                        'validation_warnings' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    if (count($buffer) >= $chunkSize) {
                        DB::connection('exam')->table('preliminary_import_staging')->insert($buffer);
                        $staged += count($buffer);
                        $buffer = [];
                        $this->updateProgress($batch, $staged, $totalRows);
                    }
                }

                break;
            }

            if ($buffer !== []) {
                DB::connection('exam')->table('preliminary_import_staging')->insert($buffer);
                $staged += count($buffer);
                $this->updateProgress($batch, $staged, $totalRows);
            }

            $reader->close();
            $reader = null;

            $batch->update([
                'status' => 'staged',
                'total_rows' => $staged,
                'processed_rows' => $staged,
                'staged_rows' => $staged,
                'progress_percent' => 100,
                'finished_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            if ($reader !== null) {
                try { $reader->close(); } catch (Throwable) {}
            }

            $batch->update([
                'status' => 'failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    /** @param array<int,mixed> $values @return list<string> */
    private function validateHeaders(array $values): array
    {
        $actual = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            array_slice($values, 0, 4),
        );
        $expected = array_values((array) config('preliminary.headers'));

        if ($actual !== $expected) {
            throw new RuntimeException('Headers do not match the preliminary template. Expected: '.implode(', ', $expected));
        }

        return $expected;
    }

    /** @param array<int,mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach (array_slice($values, 0, 4) as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function updateProgress(PreliminaryImportBatch $batch, int $staged, int $totalRows): void
    {
        $batch->update([
            'processed_rows' => $staged,
            'staged_rows' => $staged,
            'progress_percent' => $totalRows > 0 ? min(99.9, round(($staged / $totalRows) * 100, 4)) : 0,
        ]);
    }

    /** Read XLSX worksheet dimension without parsing the workbook a second time. */
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

    private function rawText(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return trim((string) $value);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
