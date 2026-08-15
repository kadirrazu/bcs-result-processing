<?php

namespace App\Services\Registrations;

use App\Jobs\ProcessRegistrationImport;
use App\Models\RegistrationImportBatch;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * R5 fast-ingestion service.
 *
 * This stage only streams the source file into a lightly indexed staging table.
 * Master-data and business-rule validation deliberately run in a later job.
 */
final class RegistrationImportService
{
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
            'chunk_size' => max(500, (int) config('registrations.staging_chunk_size', 2000)),
            'queued_at' => now(),
            'created_by' => $userId,
        ]);

        ProcessRegistrationImport::dispatch($examinationId, $batch->id);

        return $batch;
    }

    public function process(int $batchId): RegistrationImportBatch
    {
        $batch = RegistrationImportBatch::query()->findOrFail($batchId);
        $path = Storage::disk('local')->path($batch->stored_name);

        if (! is_file($path)) {
            throw new RuntimeException('The uploaded registration spreadsheet is missing.');
        }

        $batch->update([
            'status' => 'staging',
            'started_at' => $batch->started_at ?? now(),
            'heartbeat_at' => now(),
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
                default => throw new RuntimeException('Registration staging supports XLSX and CSV files only.'),
            };

            $totalRows = $this->quickTotalRows($path, $extension);
            $requestedChunkSize = max(500, (int) $batch->chunk_size);
            $stagingColumnCount = count($this->toStagingRow(
                $batchId,
                0,
                [],
                now()->format('Y-m-d H:i:s'),
            ));
            $chunkSize = $this->effectiveStagingChunkSize(
                $requestedChunkSize,
                $totalRows,
                $stagingColumnCount,
            );
            $totalChunks = $totalRows > 0 ? (int) ceil($totalRows / $chunkSize) : 0;

            $batch->update([
                'total_rows' => $totalRows,
                'total_chunks' => $totalChunks,
            ]);

            $reader->open($path);
            $headers = null;
            $buffer = [];
            $sourceRow = 0;
            $staged = 0;
            $chunk = 0;
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

                    $buffer[] = $this->toStagingRow($batchId, $sourceRow, $raw, $timestamp);

                    if (count($buffer) >= $chunkSize) {
                        DB::connection('exam')->table('registration_import_staging')->insert($buffer);
                        $staged += count($buffer);
                        $chunk++;
                        $buffer = [];
                        $this->updateProgress($batch, $staged, $sourceRow, $chunk, $totalRows, $totalChunks);
                        $this->yieldAfterStagingWrite();
                    }
                }

                break;
            }

            if ($buffer !== []) {
                DB::connection('exam')->table('registration_import_staging')->insert($buffer);
                $staged += count($buffer);
                $chunk++;
                $this->updateProgress($batch, $staged, $sourceRow, $chunk, $totalRows, $totalChunks);
                $this->yieldAfterStagingWrite();
            }

            $reader->close();
            $reader = null;

            $batch->update([
                'status' => 'staged',
                'total_rows' => $totalRows > 0 ? $totalRows : $staged,
                'processed_rows' => $staged,
                'staged_rows' => $staged,
                'current_row' => $sourceRow,
                'current_chunk' => $chunk,
                'total_chunks' => $totalChunks > 0 ? $totalChunks : $chunk,
                'progress_percent' => 100,
                'finished_at' => now(),
                'heartbeat_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            if ($reader !== null) {
                try {
                    $reader->close();
                } catch (Throwable) {
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

    /** @param array<string,mixed> $raw @return array<string,mixed> */
    private function toStagingRow(int $batchId, int $sourceRow, array $raw, string $timestamp): array
    {
        $ff = $this->plain($raw['has_ff_quota'] ?? null);
        $em = $this->plain($raw['has_em_quota'] ?? null);
        $phc = $this->plain($raw['has_phc_quota'] ?? null);

        return [
            'batch_id' => $batchId,
            'source_row' => $sourceRow,
            'user_id' => strtoupper($this->plain($raw['user'] ?? null)),
            'reg' => $this->plain($raw['reg'] ?? null),
            'national_id' => $this->nullable($raw['national_id'] ?? null),
            'name' => $this->nullable($raw['name'] ?? null),
            'father_name' => $this->nullable($raw['fname'] ?? null),
            'mother_name' => $this->nullable($raw['mname'] ?? null),
            'name_bn' => $this->nullable($raw['name_bn'] ?? null),
            'father_name_bn' => $this->nullable($raw['fname_bn'] ?? null),
            'mother_name_bn' => $this->nullable($raw['mname_bn'] ?? null),
            'raw_birth_date' => $this->rawDate($raw['b_date'] ?? null),
            'birth_date' => null,
            'ssc_roll' => $this->nullable($raw['ssc_roll'] ?? null),
            'ssc_year' => $this->nullable($raw['ssc_year'] ?? null),
            'hsc_roll' => $this->nullable($raw['hsc_roll'] ?? null),
            'hsc_year' => $this->nullable($raw['hsc_year'] ?? null),
            'graduation_year' => $this->nullable($raw['graduation_year'] ?? null),
            'sex_code' => $this->nullable($raw['sex'] ?? null),
            'district_code' => $this->nullable($raw['district'] ?? null),
            'division_code' => null,
            'university_code' => $this->nullable($raw['university'] ?? null),
            'bachelor_subject_code' => $this->nullable($raw['b_subject'] ?? null),
            'post_related_subject_code' => $this->nullable($raw['post_related_subject'] ?? null),
            'cadre_category' => $this->nullable($raw['cadre_category'] ?? null),
            'has_ff_quota' => $ff === '' ? null : $ff,
            'has_em_quota' => $em === '' ? null : $em,
            'has_phc_quota' => $phc === '' ? null : $phc,
            'has_quota' => $this->hasQuota($ff, $em, $phc),
            'candidate_status' => strtolower($this->plain($raw['status'] ?? null) ?: 'active'),
            'comment' => $this->nullable($raw['comment'] ?? null),
            'validation_status' => 'pending',
            'validation_errors' => null,
            'validation_warnings' => null,
            'registration_id' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function updateProgress(
        RegistrationImportBatch $batch,
        int $staged,
        int $sourceRow,
        int $chunk,
        int $totalRows,
        int $totalChunks,
    ): void {
        $batch->update([
            'processed_rows' => $staged,
            'staged_rows' => $staged,
            'current_row' => $sourceRow,
            'current_chunk' => $chunk,
            'total_chunks' => $totalChunks,
            'progress_percent' => $totalRows > 0 ? min(100, round(($staged / $totalRows) * 100, 4)) : 0,
            'heartbeat_at' => now(),
        ]);
    }

    /** @param list<mixed> $values @return list<string> */
    private function validateHeaders(array $values): array
    {
        $required = array_values((array) config('registrations.required_headers', []));
        $allowed = array_values((array) config('registrations.headers', []));
        $headers = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $values,
        );

        // Spreadsheet readers may include unused trailing cells; they are not source headers.
        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        if ($headers === [] || in_array('', $headers, true)) {
            throw new RuntimeException('Registration source contains a blank header.');
        }
        if (count($headers) !== count(array_unique($headers))) {
            throw new RuntimeException('Registration source contains duplicate headers.');
        }

        $unknown = array_values(array_diff($headers, $allowed));
        if ($unknown !== []) {
            throw new RuntimeException('Unknown registration header(s): '.implode(', ', $unknown).'.');
        }

        $missing = array_values(array_diff($required, $headers));
        if ($missing !== []) {
            throw new RuntimeException('Missing required registration header(s): '.implode(', ', $missing).'.');
        }

        return $headers;
    }

    /** @param array<int,mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value instanceof DateTimeInterface) {
                return false;
            }
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function plain(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('dmY');
        }

        return trim((string) ($value ?? ''));
    }

    private function nullable(mixed $value): ?string
    {
        $value = $this->plain($value);

        return $value === '' ? null : $value;
    }

    private function rawDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('dmY');
        }

        return $this->nullable($value);
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

    private function effectiveStagingChunkSize(int $requestedRows, int $totalRows, int $columnCount): int
    {
        $largeThreshold = max(1, (int) config('registrations.large_import_threshold', 100000));
        $largeChunkSize = max(500, (int) config('registrations.large_staging_chunk_size', 500));

        $effectiveRequestedRows = $totalRows >= $largeThreshold
            ? min($requestedRows, $largeChunkSize)
            : $requestedRows;

        return $this->safeBulkWriteSize($effectiveRequestedRows, $columnCount);
    }

    private function yieldAfterStagingWrite(): void
    {
        $milliseconds = max(0, (int) config('registrations.staging_throttle_ms', 15));
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function safeBulkWriteSize(int $requestedRows, int $columnCount): int
    {
        $budget = max(1000, (int) config('registrations.bulk_placeholder_budget', 60000));
        $maxRowsByPlaceholders = max(1, intdiv($budget, max(1, $columnCount)));

        return max(1, min($requestedRows, $maxRowsByPlaceholders));
    }

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
