<?php

namespace App\Services\Registrations;

use App\Models\RegistrationImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/** Stream a spreadsheet in bounded windows and persist auditable inserts/updates. */
final class RegistrationImportService
{
    public function __construct(
        private readonly RegistrationMasterMap $maps,
        private readonly RegistrationRowNormalizer $normalizer,
        private readonly RegistrationRowValidator $validator,
        private readonly RegistrationUniversityCodePolicy $universityPolicy,
    ) {}

    public function import(UploadedFile $file, int $userId): RegistrationImportBatch
    {
        $storedName = sprintf('registration-imports/%s-%s.%s', now()->format('YmdHis'), bin2hex(random_bytes(4)), $file->getClientOriginalExtension());
        $file->storeAs(dirname($storedName), basename($storedName), 'local');

        $batch = RegistrationImportBatch::query()->create([
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'status' => 'processing',
            'started_at' => now(),
            'created_by' => $userId,
        ]);

        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $worksheetInfo = $reader->listWorksheetInfo($file->getRealPath());
            $highestRow = (int) ($worksheetInfo[0]['totalRows'] ?? 0);
            if ($highestRow < 1) {
                throw new RuntimeException('Spreadsheet is empty.');
            }

            $masters = $this->maps->load();
            $chunkSize = max(500, (int) config('registrations.chunk_size', 2000));
            $totals = ['total' => 0, 'inserted' => 0, 'updated' => 0, 'failed' => 0, 'warning' => 0, 'conflict' => 0];
            $seenRegs = [];
            $seenUserIds = [];

            for ($start = 2; $start <= $highestRow; $start += $chunkSize) {
                $end = min($highestRow, $start + $chunkSize - 1);
                $reader->setReadFilter(new RegistrationChunkReadFilter($start, $end));
                $book = $reader->load($file->getRealPath());
                $sheet = $book->getActiveSheet();
                $columnCount = count(config('registrations.headers'));
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
                $headerValues = $sheet->rangeToArray("A1:{$lastColumn}1", null, true, true, false)[0] ?? [];
                $headers = array_map(static fn (mixed $value): string => strtolower(trim((string) $value)), $headerValues);
                if ($headers !== config('registrations.headers')) {
                    throw new RuntimeException('Headers do not match the downloaded registration template.');
                }
                $rows = $sheet->rangeToArray("A{$start}:{$lastColumn}{$end}", null, true, true, false);

                $prepared = [];
                foreach ($rows as $offset => $values) {
                    if (collect($values)->every(static fn (mixed $value): bool => $value === null || trim((string) $value) === '')) {
                        continue;
                    }

                    $sourceRow = $start + $offset;
                    $totals['total']++;
                    $raw = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
                    $normalized = $this->normalizer->normalize($raw, $batch->id);
                    $data = $normalized['attributes'];

                    // Division is authoritative on the district master and is never trusted from registration Excel.
                    $data['division_code'] = $data['district_code'] === null
                        ? null
                        : ($masters['district_division'][(string) $data['district_code']] ?? null);
                    $normalized['attributes'] = $data;

                    // University is optional and never blocks result processing. Preserve an
                    // unknown source code so a later master-data addition resolves it automatically.
                    $universityResult = $this->universityPolicy->apply(
                        $normalized['attributes'],
                        $masters['university'],
                    );
                    $normalized['attributes'] = $universityResult['attributes'];
                    $normalized['warnings'] = array_values(array_unique([
                        ...$normalized['warnings'],
                        ...$universityResult['warnings'],
                    ]));
                    $data = $normalized['attributes'];

                    $errors = $this->validator->validate($data, $masters);
                    if (($data['reg'] !== '' && isset($seenRegs[$data['reg']]))
                        || ($data['user_id'] !== '' && isset($seenUserIds[$data['user_id']]))) {
                        $errors[] = 'Duplicate REG or USER appears more than once in the same spreadsheet.';
                    }
                    if ($data['reg'] !== '') {
                        $seenRegs[$data['reg']] = true;
                    }
                    if ($data['user_id'] !== '') {
                        $seenUserIds[$data['user_id']] = true;
                    }

                    $prepared[] = [
                        'source_row' => $sourceRow,
                        'data' => $normalized['attributes'],
                        'warnings' => $normalized['warnings'],
                        'errors' => $errors,
                    ];
                }

                $chunkTotals = $this->persistChunk($batch->id, $prepared);
                foreach ($chunkTotals as $key => $value) {
                    $totals[$key] += $value;
                }

                $book->disconnectWorksheets();
                unset($book, $rows, $prepared);
            }

            $batch->update([
                'status' => $totals['failed'] > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $totals['total'],
                'inserted_rows' => $totals['inserted'],
                'updated_rows' => $totals['updated'],
                'failed_rows' => $totals['failed'],
                'warning_rows' => $totals['warning'],
                'identity_conflict_rows' => $totals['conflict'],
                'finished_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            $batch->update(['status' => 'failed', 'finished_at' => now()]);
            throw $exception;
        }
    }

    /**
     * @param list<array{source_row:int,data:array<string,mixed>,warnings:list<string>,errors:list<string>}> $rows
     * @return array{inserted:int,updated:int,failed:int,warning:int,conflict:int}
     */
    private function persistChunk(int $batchId, array $rows): array
    {
        $totals = ['inserted' => 0, 'updated' => 0, 'failed' => 0, 'warning' => 0, 'conflict' => 0];
        if ($rows === []) {
            return $totals;
        }

        $validRows = array_values(array_filter($rows, static fn (array $row): bool => $row['errors'] === []));
        $regs = array_values(array_unique(array_column(array_column($validRows, 'data'), 'reg')));
        $userIds = array_values(array_unique(array_column(array_column($validRows, 'data'), 'user_id')));
        $existing = DB::connection('exam')->table('registrations')
            ->whereIn('reg', $regs)
            ->orWhereIn('user_id', $userIds)
            ->get()
            ->map(static fn (object $row): array => (array) $row);

        $byReg = $existing->keyBy('reg');
        $byUser = $existing->keyBy('user_id');

        DB::connection('exam')->transaction(function () use ($batchId, $rows, $byReg, $byUser, &$totals): void {
            foreach ($rows as $row) {
                $data = $row['data'];
                $audit = [
                    'batch_id' => $batchId,
                    'source_row' => $row['source_row'],
                    'reg' => $data['reg'] ?: null,
                    'user_id' => $data['user_id'] ?: null,
                    'warnings' => $row['warnings'] === [] ? null : json_encode($row['warnings'], JSON_UNESCAPED_UNICODE),
                    'errors' => null,
                    'before_data' => null,
                    'after_data' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($row['errors'] !== []) {
                    $audit['action'] = 'rejected';
                    $audit['errors'] = json_encode($row['errors'], JSON_UNESCAPED_UNICODE);
                    DB::connection('exam')->table('registration_import_rows')->insert($audit);
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
                    DB::connection('exam')->table('registration_import_rows')->insert($audit);
                    $totals['failed']++;
                    $totals['conflict']++;
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

                if ($row['warnings'] !== []) {
                    $totals['warning']++;
                }
                DB::connection('exam')->table('registration_import_rows')->insert($audit);
            }
        });

        return $totals;
    }
}
