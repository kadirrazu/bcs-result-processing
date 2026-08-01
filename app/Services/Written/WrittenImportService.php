<?php

namespace App\Services\Written;

use App\Jobs\ProcessWrittenImport;
use App\Models\WrittenImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;
use ZipArchive;

/** Streams a Written XLSX/CSV source into raw staging with minimal work. */
final class WrittenImportService
{
    public function enqueue(UploadedFile $file, int $userId, int $examinationId): WrittenImportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = sprintf('written-imports/%s-%s.%s', now()->format('YmdHis'), bin2hex(random_bytes(8)), $extension);
        $file->storeAs(dirname($storedName), basename($storedName), 'local');

        $batch = WrittenImportBatch::query()->create([
            'examination_id' => $examinationId,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'status' => 'queued',
            'queued_at' => now(),
            'created_by' => $userId,
        ]);

        ProcessWrittenImport::dispatch($examinationId, $batch->id, $userId);

        return $batch;
    }

    public function process(int $batchId): WrittenImportBatch
    {
        $batch = WrittenImportBatch::query()->findOrFail($batchId);
        $path = Storage::disk('local')->path($batch->stored_name);
        if (! is_file($path)) {
            throw new RuntimeException('The uploaded Written spreadsheet is missing.');
        }

        DB::connection('exam')->table('written_import_staging')->where('batch_id', $batchId)->delete();

        $batch->update([
            'status' => 'staging', 'started_at' => $batch->started_at ?? now(), 'finished_at' => null,
            'failure_message' => null, 'processed_rows' => 0, 'staged_rows' => 0, 'progress_percent' => 0,
        ]);

        $reader = null;
        try {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $reader = match ($extension) {
                'xlsx' => new XlsxReader(),
                'csv' => new CsvReader(),
                default => throw new RuntimeException('Written import supports XLSX and CSV files only.'),
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
            $chunkSize = max(250, (int) config('written.staging_chunk_size', 3000));
            $timestamp = now()->format('Y-m-d H:i:s');

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $spreadsheetRow) {
                    $sourceRow++;
                    $values = $spreadsheetRow->toArray();
                    if ($sourceRow === 1) {
                        $headers = $this->validateHeaders($values);
                        continue;
                    }
                    if ($this->isEmptyRow($values, count($headers))) {
                        continue;
                    }

                    $raw = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
                    $user = $this->normalizeUser($raw['user'] ?? null);
                    $reg = $this->normalizeReg($raw['reg'] ?? null);
                    $prsCode = $this->normalizeCode($raw['prs_code'] ?? null);
                    $prsRaw = $this->rawText($raw['prs_mark'] ?? null);

                    $payload = [];
                    foreach ($headers as $header) {
                        $payload[$header] = $this->rawText($raw[$header] ?? null);
                    }

                    $buffer[] = [
                        'batch_id' => $batchId,
                        'source_row' => $sourceRow,
                        'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'registration_id' => null,
                        'user_id' => $user,
                        'reg' => $reg,
                        'normalized_marks' => null,
                        'prs_code' => $prsCode,
                        'prs_mark' => is_numeric($prsRaw) ? (float) $prsRaw : null,
                        'data_source_note' => $this->rawText($raw['data_source_note'] ?? null),
                        'status' => 'active',
                        'validation_status' => 'pending',
                        'validation_errors' => null,
                        'validation_warnings' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    if (count($buffer) >= $chunkSize) {
                        DB::connection('exam')->table('written_import_staging')->insert($buffer);
                        $staged += count($buffer);
                        $buffer = [];
                        $this->updateProgress($batch, $staged, $totalRows);
                    }
                }
                break;
            }

            if ($buffer !== []) {
                DB::connection('exam')->table('written_import_staging')->insert($buffer);
                $staged += count($buffer);
                $this->updateProgress($batch, $staged, $totalRows);
            }

            $reader->close();
            $reader = null;
            $batch->update([
                'status' => 'staged', 'total_rows' => $staged, 'processed_rows' => $staged,
                'staged_rows' => $staged, 'progress_percent' => 100, 'finished_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            if ($reader !== null) {
                try { $reader->close(); } catch (Throwable) {}
            }
            $batch->update(['status' => 'failed', 'failure_message' => mb_substr($exception->getMessage(), 0, 65000), 'finished_at' => now()]);
            throw $exception;
        }
    }

    /** @param array<int,mixed> $values @return list<string> */
    private function validateHeaders(array $values): array
    {
        $expected = array_values((array) config('written.headers', []));
        $received = array_map(static fn (mixed $value): string => strtolower(trim((string) $value)), array_slice($values, 0, count($expected)));
        $aliases = ['data_souce_note' => 'data_source_note'];
        $actual = array_map(static fn (string $header): string => $aliases[$header] ?? $header, $received);
        if ($actual !== $expected) {
            throw new RuntimeException('Headers do not match the Written template. Expected: '.implode(', ', $expected).' | Received: '.implode(', ', $received));
        }
        return $expected;
    }

    /** @param array<int,mixed> $values */
    private function isEmptyRow(array $values, int $columnCount): bool
    {
        foreach (array_slice($values, 0, $columnCount) as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }
        return true;
    }

    private function updateProgress(WrittenImportBatch $batch, int $staged, int $totalRows): void
    {
        $batch->update([
            'processed_rows' => $staged,
            'staged_rows' => $staged,
            'progress_percent' => $totalRows > 0 ? min(99.9, round(($staged / $totalRows) * 100, 4)) : 0,
        ]);
    }

    private function quickTotalRows(string $path, string $extension): int
    {
        if ($extension !== 'xlsx') { return 0; }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) { return 0; }
        $stream = $zip->getStream('xl/worksheets/sheet1.xml');
        if ($stream === false) { $zip->close(); return 0; }
        $prefix = fread($stream, 131072) ?: '';
        fclose($stream); $zip->close();
        if (preg_match('/<dimension[^>]+ref="(?:[A-Z]+\d+:)?[A-Z]+(\d+)"/i', $prefix, $match) !== 1) { return 0; }
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
        if (str_ends_with($value, '.0')) { $value = substr($value, 0, -2); }
        return $value === '' ? null : $value;
    }

    private function normalizeCode(mixed $value): ?string
    {
        $value = strtoupper(trim((string) ($value ?? '')));
        if (str_ends_with($value, '.0') && preg_match('/^\d+\.0$/', $value) === 1) { $value = substr($value, 0, -2); }
        return $value === '' ? null : $value;
    }

    private function rawText(mixed $value): ?string
    {
        if ($value === null) { return null; }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
