<?php

namespace App\Services\Registrations;

use App\Jobs\ProcessRegistrationImport;
use App\Models\RegistrationImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/** Queue and process registration spreadsheets in bounded memory windows. */
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
            'chunk_size' => max(250, (int) config('registrations.chunk_size', 1000)),
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

        $batch->update([
            'status' => 'processing',
            'started_at' => $batch->started_at ?? now(),
            'heartbeat_at' => now(),
            'failure_message' => null,
        ]);

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $worksheetInfo = $reader->listWorksheetInfo($path);
            $highestRow = (int) ($worksheetInfo[0]['totalRows'] ?? 0);
            if ($highestRow < 2) {
                throw new RuntimeException('Spreadsheet contains no registration rows.');
            }

            $totalRows = max(0, $highestRow - 1);
            $chunkSize = max(250, (int) $batch->chunk_size);
            $totalChunks = (int) ceil($totalRows / $chunkSize);
            $batch->update(['total_rows' => $totalRows, 'total_chunks' => $totalChunks]);

            $masters = $this->maps->load();
            $totals = ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'failed' => 0, 'warning' => 0, 'conflict' => 0];
            $headers = $this->readAndValidateHeaders($reader, $path);
            $columnCount = count($headers);
            $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

            for ($start = 2, $chunk = 1; $start <= $highestRow; $start += $chunkSize, $chunk++) {
                $end = min($highestRow, $start + $chunkSize - 1);
                $reader->setReadFilter(new RegistrationChunkReadFilter($start, $end));
                $book = $reader->load($path);
                $sheet = $book->getActiveSheet();
                $rows = $sheet->rangeToArray("A{$start}:{$lastColumn}{$end}", null, true, true, false);
                $prepared = [];

                foreach ($rows as $offset => $values) {
                    if ($this->isEmptyRow($values)) {
                        continue;
                    }

                    $sourceRow = $start + $offset;
                    $raw = array_combine($headers, array_slice(array_pad($values, $columnCount, null), 0, $columnCount));
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

                    $prepared[] = compact('sourceRow', 'data', 'warnings', 'errors');
                }

                $chunkTotals = $this->persistChunk($batch->id, $prepared);
                foreach ($chunkTotals as $key => $value) {
                    $totals[$key] += $value;
                }

                $processedPosition = min($totalRows, $end - 1);
                $batch->update([
                    'processed_rows' => $processedPosition,
                    'current_row' => $end,
                    'current_chunk' => $chunk,
                    'progress_percent' => $totalRows === 0 ? 100 : round(($processedPosition / $totalRows) * 100, 4),
                    'inserted_rows' => $totals['inserted'],
                    'updated_rows' => $totals['updated'],
                    'failed_rows' => $totals['failed'],
                    'warning_rows' => $totals['warning'],
                    'identity_conflict_rows' => $totals['conflict'],
                    'heartbeat_at' => now(),
                ]);

                $book->disconnectWorksheets();
                unset($book, $sheet, $rows, $prepared);
                gc_collect_cycles();
            }

            $batch->update([
                'status' => $totals['failed'] > 0 ? 'completed_with_errors' : 'completed',
                'processed_rows' => $totalRows,
                'progress_percent' => 100,
                'finished_at' => now(),
                'heartbeat_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                'finished_at' => now(),
                'heartbeat_at' => now(),
            ]);
            throw $exception;
        }
    }

    /** @return list<string> */
    private function readAndValidateHeaders(object $reader, string $path): array
    {
        $reader->setReadFilter(new RegistrationChunkReadFilter(1, 1));
        $book = $reader->load($path);
        $columnCount = count(config('registrations.headers'));
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $values = $book->getActiveSheet()->rangeToArray("A1:{$lastColumn}1", null, true, true, false)[0] ?? [];
        $headers = array_map(static fn (mixed $value): string => strtolower(trim((string) $value)), $values);
        $book->disconnectWorksheets();
        unset($book);

        if ($headers !== config('registrations.headers')) {
            throw new RuntimeException('Headers do not match the downloaded registration template.');
        }

        return $headers;
    }

    /** @param array<int,mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param list<array{sourceRow:int,data:array<string,mixed>,warnings:list<string>,errors:list<string>}> $rows
     * @return array{processed:int,inserted:int,updated:int,failed:int,warning:int,conflict:int}
     */
    private function persistChunk(int $batchId, array $rows): array
    {
        $totals = ['processed' => count($rows), 'inserted' => 0, 'updated' => 0, 'failed' => 0, 'warning' => 0, 'conflict' => 0];
        if ($rows === []) {
            return $totals;
        }

        $validRows = array_values(array_filter($rows, static fn (array $row): bool => $row['errors'] === []));
        $regs = array_values(array_unique(array_filter(array_column(array_column($validRows, 'data'), 'reg'))));
        $userIds = array_values(array_unique(array_filter(array_column(array_column($validRows, 'data'), 'user_id'))));

        $existing = collect();
        $priorRows = collect();
        if ($regs !== [] || $userIds !== []) {
            $existing = DB::connection('exam')->table('registrations')
                ->where(function ($query) use ($regs, $userIds): void {
                    if ($regs !== []) {
                        $query->whereIn('reg', $regs);
                    }
                    if ($userIds !== []) {
                        $method = $regs === [] ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('user_id', $userIds);
                    }
                })->get()->map(static fn (object $row): array => (array) $row);

            $priorRows = DB::connection('exam')->table('registration_import_rows')
                ->where('batch_id', $batchId)
                ->where(function ($query) use ($regs, $userIds): void {
                    if ($regs !== []) {
                        $query->whereIn('reg', $regs);
                    }
                    if ($userIds !== []) {
                        $method = $regs === [] ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('user_id', $userIds);
                    }
                })->get(['reg', 'user_id']);
        }

        $seenRegs = $priorRows->pluck('reg')->filter()->flip();
        $seenUsers = $priorRows->pluck('user_id')->filter()->flip();
        $byReg = $existing->keyBy('reg');
        $byUser = $existing->keyBy('user_id');
        $auditRows = [];

        DB::connection('exam')->transaction(function () use ($batchId, $rows, $byReg, $byUser, $seenRegs, $seenUsers, &$auditRows, &$totals): void {
            foreach ($rows as $row) {
                $data = $row['data'];
                $errors = $row['errors'];
                if (($data['reg'] && $seenRegs->has($data['reg'])) || ($data['user_id'] && $seenUsers->has($data['user_id']))) {
                    $errors[] = 'Duplicate REG or USER appears more than once in the same spreadsheet.';
                }
                if ($data['reg']) { $seenRegs->put($data['reg'], true); }
                if ($data['user_id']) { $seenUsers->put($data['user_id'], true); }

                $audit = [
                    'batch_id' => $batchId,
                    'source_row' => $row['sourceRow'],
                    'reg' => $data['reg'] ?: null,
                    'user_id' => $data['user_id'] ?: null,
                    'warnings' => $row['warnings'] === [] ? null : json_encode($row['warnings'], JSON_UNESCAPED_UNICODE),
                    'errors' => null, 'before_data' => null, 'after_data' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ];

                if ($errors !== []) {
                    $audit['action'] = 'rejected';
                    $audit['errors'] = json_encode(array_values(array_unique($errors)), JSON_UNESCAPED_UNICODE);
                    $auditRows[] = $audit;
                    $totals['failed']++;
                    continue;
                }

                $regMatch = $byReg->get($data['reg']);
                $userMatch = $byUser->get($data['user_id']);
                if (($regMatch && $regMatch['user_id'] !== $data['user_id'])
                    || ($userMatch && $userMatch['reg'] !== $data['reg'])
                    || ($regMatch && $userMatch && $regMatch['id'] !== $userMatch['id'])) {
                    $audit['action'] = 'identity_conflict';
                    $audit['errors'] = json_encode(['REG and USER identify different candidates. Correct the spreadsheet and import again.'], JSON_UNESCAPED_UNICODE);
                    $auditRows[] = $audit;
                    $totals['failed']++; $totals['conflict']++;
                    continue;
                }

                if ($regMatch) {
                    $before = $regMatch;
                    unset($data['created_at']);
                    DB::connection('exam')->table('registrations')->where('id', $regMatch['id'])->update($data);
                    $after = DB::connection('exam')->table('registrations')->where('id', $regMatch['id'])->first();
                    $audit['registration_id'] = $regMatch['id'];
                    $audit['action'] = 'updated';
                    $audit['before_data'] = json_encode($before, JSON_UNESCAPED_UNICODE);
                    $audit['after_data'] = json_encode((array) $after, JSON_UNESCAPED_UNICODE);
                    $totals['updated']++;
                } else {
                    $registrationId = DB::connection('exam')->table('registrations')->insertGetId($data);
                    $after = DB::connection('exam')->table('registrations')->where('id', $registrationId)->first();
                    $audit['registration_id'] = $registrationId;
                    $audit['action'] = 'inserted';
                    $audit['after_data'] = json_encode((array) $after, JSON_UNESCAPED_UNICODE);
                    $totals['inserted']++;
                }

                if ($row['warnings'] !== []) { $totals['warning']++; }
                $auditRows[] = $audit;
            }

            foreach (array_chunk($auditRows, 500) as $auditChunk) {
                DB::connection('exam')->table('registration_import_rows')->insert($auditChunk);
            }
        });

        return $totals;
    }
}
