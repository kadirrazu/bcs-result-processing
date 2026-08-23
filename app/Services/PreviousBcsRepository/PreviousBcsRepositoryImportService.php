<?php

namespace App\Services\PreviousBcsRepository;

use App\Jobs\ProcessPreviousBcsRepositoryImport;
use App\Models\PreviousBcsRepository;
use App\Models\PreviousBcsRepositoryDataset;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;
use ZipArchive;

final class PreviousBcsRepositoryImportService
{
    public const COLUMNS = [
        'reg', 'name', 'fname', 'mname', 'b_date', 'dob', 'dist_name',
        'ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'nid_no', 'cadre',
    ];

    public const OPTIONAL_COLUMNS = ['fname', 'mname', 'dob', 'dist_name', 'nid_no'];

    public function __construct(
        private readonly PreviousBcsDateNormalizer $dates,
        private readonly PreviousBcsRepositoryAuditService $audit,
    ) {}

    public function enqueue(int $bcsNumber, UploadedFile $file, int $actorId): PreviousBcsRepositoryDataset
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw new RuntimeException('Previous BCS repository import supports XLSX or CSV files.');
        }

        return DB::transaction(function () use ($bcsNumber, $file, $actorId, $extension): PreviousBcsRepositoryDataset {
            $repository = PreviousBcsRepository::query()->firstOrCreate(['bcs_number' => $bcsNumber]);

            // Lock the repository row so concurrent uploads cannot create the same version.
            $repository = PreviousBcsRepository::query()->lockForUpdate()->findOrFail($repository->id);
            $version = ((int) $repository->datasets()->max('version')) + 1;

            $storedName = sprintf(
                'previous-bcs-repository/bcs-%d/v%d/%s-%s.%s',
                $bcsNumber,
                $version,
                now()->format('YmdHis'),
                bin2hex(random_bytes(6)),
                $extension,
            );
            $file->storeAs(dirname($storedName), basename($storedName), 'local');

            $dataset = $repository->datasets()->create([
                'version' => $version,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'status' => 'queued',
                'queued_at' => now(),
                'created_by' => $actorId,
            ]);

            $this->audit->record('DATASET_QUEUED', $repository->id, $dataset->id, $actorId, [
                'bcs_number' => $bcsNumber,
                'version' => $version,
                'original_name' => $file->getClientOriginalName(),
            ]);

            ProcessPreviousBcsRepositoryImport::dispatch((int) $dataset->id, $actorId);

            return $dataset;
        });
    }

    public function process(int $datasetId, int $actorId): PreviousBcsRepositoryDataset
    {
        $dataset = PreviousBcsRepositoryDataset::query()->with('repository')->findOrFail($datasetId);
        $path = Storage::disk('local')->path($dataset->stored_name);
        if (! is_file($path)) {
            throw new RuntimeException('The uploaded Previous BCS repository spreadsheet is missing.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = $extension === 'csv' ? new CsvReader() : new XlsxReader();

        $dataset->rows()->delete();
        $dataset->update([
            'status' => 'processing',
            'started_at' => now(),
            'processed_rows' => 0,
            'staged_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'finished_at' => null,
        ]);

        $this->audit->record('DATASET_PROCESSING_STARTED', $dataset->repository_id, $dataset->id, $actorId);

        try {
            $estimatedTotal = $this->estimatedRows($path, $extension);
            $reader->open($path);
            $headers = null;
            $buffer = [];
            $processed = 0;
            $staged = 0;
            $valid = 0;
            $invalid = 0;
            $sourceRow = 0;
            $chunkSize = 1000;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $sourceRow++;
                    $values = $row->toArray();

                    if ($headers === null) {
                        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $values);
                        $this->assertHeaders($headers);
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

                    $payload['__dataset_id'] = $dataset->id;
                    $result = $this->normalizeRow($payload, $sourceRow);
                    $buffer[] = $result['row'];
                    $processed++;
                    $result['valid'] ? $valid++ : $invalid++;

                    if (count($buffer) >= $chunkSize) {
                        DB::table('previous_bcs_repository_rows')->insert($buffer);
                        $staged += count($buffer);
                        $buffer = [];
                        $this->progress($dataset, $processed, $staged, $valid, $invalid, $estimatedTotal);
                    }
                }
                break;
            }

            if ($buffer !== []) {
                DB::table('previous_bcs_repository_rows')->insert($buffer);
                $staged += count($buffer);
            }

            $reader->close();

            $dataset->update([
                'status' => 'staged',
                'total_rows' => $processed,
                'processed_rows' => $processed,
                'staged_rows' => $staged,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'progress_percent' => 100,
                'staged_at' => now(),
                'finished_at' => now(),
            ]);

            $this->audit->record('DATASET_STAGED', $dataset->repository_id, $dataset->id, $actorId, [
                'total_rows' => $processed,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
            ]);

            return $dataset->refresh();
        } catch (Throwable $e) {
            try {
                $reader->close();
            } catch (Throwable) {
            }

            $dataset->update([
                'status' => 'failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);

            $this->audit->record('DATASET_FAILED', $dataset->repository_id, $dataset->id, $actorId, [
                'message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e;
        }
    }

    private function assertHeaders(array $headers): void
    {
        if ($headers !== self::COLUMNS) {
            throw new RuntimeException(
                'Previous BCS repository headers must exactly match: '.implode(', ', self::COLUMNS)
            );
        }
    }

    /** @return array{row:array<string,mixed>,valid:bool} */
    private function normalizeRow(array $payload, int $sourceRow): array
    {
        $errors = [];
        $required = array_values(array_diff(self::COLUMNS, self::OPTIONAL_COLUMNS));

        foreach ($required as $field) {
            if ($this->rawText($payload[$field] ?? null) === null) {
                $errors[] = ['field' => $field, 'code' => 'REQUIRED', 'message' => "{$field} is required."];
            }
        }

        $bDate = $this->dates->bDate($payload['b_date'] ?? null);
        if ($bDate['error']) {
            $errors[] = ['field' => 'b_date', 'code' => 'INVALID_B_DATE', 'message' => $bDate['error']];
        }

        $dob = $this->dates->optionalDob($payload['dob'] ?? null);
        if ($dob['error']) {
            $errors[] = ['field' => 'dob', 'code' => 'INVALID_DOB', 'message' => $dob['error']];
        }

        $sscYear = $this->year($payload['ssc_year'] ?? null, 'ssc_year', $errors);
        $hscYear = $this->year($payload['hsc_year'] ?? null, 'hsc_year', $errors);

        $row = [
            'dataset_id' => null, // replaced immediately below by caller buffer preparation hook
        ];

        // Dataset id is injected by reading the current model through the payload sentinel.
        $datasetId = (int) ($payload['__dataset_id'] ?? 0);
        unset($payload['__dataset_id']);

        $row = [
            'dataset_id' => $datasetId,
            'source_row' => $sourceRow,
            'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'reg' => $this->identity($payload['reg'] ?? null),
            'name' => $this->rawText($payload['name'] ?? null),
            'fname' => $this->rawText($payload['fname'] ?? null),
            'mname' => $this->rawText($payload['mname'] ?? null),
            'b_date_raw' => $bDate['raw'],
            'b_date' => $bDate['date'],
            'dob_raw' => $dob['raw'],
            'dob' => $dob['date'],
            'dist_name' => $this->rawText($payload['dist_name'] ?? null),
            'ssc_roll' => $this->identity($payload['ssc_roll'] ?? null),
            'ssc_year' => $sscYear,
            'hsc_roll' => $this->identity($payload['hsc_roll'] ?? null),
            'hsc_year' => $hscYear,
            'nid_no' => $this->identity($payload['nid_no'] ?? null),
            'cadre' => $this->rawText($payload['cadre'] ?? null),
            'validation_status' => $errors === [] ? 'ready_for_validation' : 'invalid_source',
            'validation_errors' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return ['row' => $row, 'valid' => $errors === []];
    }

    private function year(mixed $value, string $field, array &$errors): ?int
    {
        $raw = $this->identity($value);
        if ($raw === null || preg_match('/^\d{4}$/', $raw) !== 1) {
            $errors[] = ['field' => $field, 'code' => 'INVALID_YEAR', 'message' => "{$field} must be a four-digit year."];
            return null;
        }

        $year = (int) $raw;
        if ($year < 1900 || $year > ((int) date('Y') + 1)) {
            $errors[] = ['field' => $field, 'code' => 'INVALID_YEAR_RANGE', 'message' => "{$field} is outside the accepted year range."];
            return null;
        }

        return $year;
    }

    private function rawText(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) ($value ?? ''));
        return $raw === '' ? null : $raw;
    }

    private function identity(mixed $value): ?string
    {
        $raw = $this->rawText($value);
        if ($raw !== null && preg_match('/^(\d+)\.0+$/', $raw, $matches) === 1) {
            $raw = $matches[1];
        }
        return $raw;
    }

    private function emptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value instanceof DateTimeInterface || trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }
        return true;
    }

    private function progress(
        PreviousBcsRepositoryDataset $dataset,
        int $processed,
        int $staged,
        int $valid,
        int $invalid,
        int $estimatedTotal,
    ): void {
        $percent = $estimatedTotal > 0 ? min(99.9, round(($processed / $estimatedTotal) * 100, 4)) : 0;

        $dataset->update([
            'processed_rows' => $processed,
            'staged_rows' => $staged,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'progress_percent' => $percent,
        ]);
    }

    private function estimatedRows(string $path, string $extension): int
    {
        if ($extension !== 'xlsx') {
            return 0;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return 0;
        }

        $stream = $zip->getStream('xl/worksheets/sheet1.xml');
        if (! $stream) {
            $zip->close();
            return 0;
        }

        $xml = fread($stream, 131072) ?: '';
        fclose($stream);
        $zip->close();

        return preg_match('/<dimension[^>]+ref="(?:[A-Z]+\d+:)?[A-Z]+(\d+)"/i', $xml, $matches)
            ? max(0, ((int) $matches[1]) - 1)
            : 0;
    }
}
