<?php

namespace App\Services\Preliminary;

use App\Models\PreliminaryImportBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Validates staged preliminary rows against registration identity in set-oriented chunks. */
final class PreliminaryValidationService
{
    public function __construct(private readonly PreliminaryRowInterpreter $interpreter) {}

    public function validate(int $batchId): PreliminaryImportBatch
    {
        $batch = PreliminaryImportBatch::query()->findOrFail($batchId);

        if (! in_array($batch->status, ['staged', 'validation_queued', 'failed', 'validated'], true)) {
            throw new RuntimeException('This preliminary batch cannot be validated from its current status.');
        }

        $batch->update([
            'status' => 'validating',
            'processed_rows' => 0,
            'valid_rows' => 0,
            'warning_rows' => 0,
            'invalid_rows' => 0,
            'identity_conflict_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
        ]);

        try {
            $total = DB::connection('exam')->table('preliminary_import_staging')->where('batch_id', $batchId)->count();
            $duplicateRegs = DB::connection('exam')->table('preliminary_import_staging')
                ->where('batch_id', $batchId)->whereNotNull('reg')
                ->select('reg')->groupBy('reg')->havingRaw('COUNT(*) > 1')->pluck('reg')->flip();
            $duplicateUsers = DB::connection('exam')->table('preliminary_import_staging')
                ->where('batch_id', $batchId)->whereNotNull('user_id')
                ->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->pluck('user_id')->flip();

            $processed = $valid = $warnings = $invalid = $identityConflicts = 0;
            $chunkSize = max(500, (int) config('preliminary.validation_chunk_size', 5000));
            $writeChunk = max(250, (int) config('preliminary.validation_write_chunk_size', 2000));

            DB::connection('exam')->table('preliminary_import_staging')
                ->where('batch_id', $batchId)
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (
                    $batch, $batchId, $duplicateRegs, $duplicateUsers, $total, $writeChunk,
                    &$processed, &$valid, &$warnings, &$invalid, &$identityConflicts
                ): void {
                    $regs = $rows->pluck('reg')->filter()->unique()->values()->all();
                    $users = $rows->pluck('user_id')->filter()->unique()->values()->all();

                    $registrationRows = ($regs === [] && $users === [])
                        ? collect()
                        : DB::connection('exam')->table('registrations')
                            ->where(function ($query) use ($regs, $users): void {
                                if ($regs !== []) { $query->whereIn('reg', $regs); }
                                if ($users !== []) {
                                    $regs !== [] ? $query->orWhereIn('user_id', $users) : $query->whereIn('user_id', $users);
                                }
                            })
                            ->get(['id', 'reg', 'user_id'])
                            ->values();

                    $byReg = $registrationRows->keyBy('reg');
                    $byUser = $registrationRows->keyBy('user_id');
                    $updates = [];
                    $timestamp = now()->format('Y-m-d H:i:s');

                    foreach ($rows as $row) {
                        $errors = [];
                        $rowWarnings = [];
                        $identityConflict = false;

                        if ($row->reg === null || ! preg_match('/^\d{8}$/', (string) $row->reg)) {
                            $errors[] = 'REG must contain exactly 8 digits.';
                        }
                        if ($row->user_id === null || ! preg_match('/^[A-Z0-9]{1,10}$/', (string) $row->user_id)) {
                            $errors[] = 'USER must be an alphanumeric value of up to 10 characters.';
                        }
                        if ($row->reg !== null && isset($duplicateRegs[$row->reg])) {
                            $errors[] = 'REG appears more than once in this import batch.';
                            $identityConflict = true;
                        }
                        if ($row->user_id !== null && isset($duplicateUsers[$row->user_id])) {
                            $errors[] = 'USER appears more than once in this import batch.';
                            $identityConflict = true;
                        }

                        $byRegRow = $row->reg === null ? null : $byReg->get($row->reg);
                        $byUserRow = $row->user_id === null ? null : $byUser->get($row->user_id);
                        $registrationId = null;

                        if ($byRegRow === null && $byUserRow === null) {
                            $errors[] = 'No registration matches this REG and USER.';
                            $identityConflict = true;
                        } elseif ($byRegRow === null || $byUserRow === null || (int) $byRegRow->id !== (int) $byUserRow->id) {
                            $errors[] = 'REG and USER do not identify the same registered candidate.';
                            $identityConflict = true;
                        } elseif ((string) $byRegRow->user_id !== (string) $row->user_id || (string) $byUserRow->reg !== (string) $row->reg) {
                            $errors[] = 'REG and USER identity mismatch.';
                            $identityConflict = true;
                        } else {
                            $registrationId = (int) $byRegRow->id;
                        }

                        $interpreted = $this->interpreter->interpret($row->raw_mark, $row->raw_candidate_status);
                        $errors = array_merge($errors, $interpreted['errors']);
                        $rowWarnings = array_merge($rowWarnings, $interpreted['warnings']);

                        if ($identityConflict) {
                            $status = 'identity_conflict';
                            $identityConflicts++;
                            $invalid++;
                        } elseif ($errors !== []) {
                            $status = 'invalid';
                            $invalid++;
                        } elseif ($rowWarnings !== []) {
                            $status = 'warning';
                            $warnings++;
                        } else {
                            $status = 'valid';
                            $valid++;
                        }

                        $updates[] = [
                            'id' => $row->id,
                            'batch_id' => $row->batch_id,
                            'source_row' => $row->source_row,
                            'registration_id' => $registrationId,
                            'user_id' => $row->user_id,
                            'reg' => $row->reg,
                            'mark' => $interpreted['mark'],
                            'candidate_status' => $interpreted['candidate_status'],
                            'validation_status' => $status,
                            'validation_errors' => $errors === [] ? null : json_encode(array_values(array_unique($errors)), JSON_UNESCAPED_UNICODE),
                            'validation_warnings' => $rowWarnings === [] ? null : json_encode(array_values(array_unique($rowWarnings)), JSON_UNESCAPED_UNICODE),
                            'updated_at' => $timestamp,
                        ];
                    }

                    foreach (array_chunk($updates, $writeChunk) as $writeRows) {
                        DB::connection('exam')->table('preliminary_import_staging')->upsert(
                            $writeRows,
                            ['id'],
                            ['registration_id', 'user_id', 'reg', 'mark', 'candidate_status', 'validation_status', 'validation_errors', 'validation_warnings', 'updated_at'],
                        );
                    }

                    $processed += $rows->count();
                    $batch->update([
                        'processed_rows' => $processed,
                        'valid_rows' => $valid,
                        'warning_rows' => $warnings,
                        'invalid_rows' => $invalid,
                        'identity_conflict_rows' => $identityConflicts,
                        'progress_percent' => $total > 0 ? min(100, round(($processed / $total) * 100, 4)) : 100,
                    ]);
                });

            $batch->update([
                'status' => 'validated',
                'processed_rows' => $processed,
                'valid_rows' => $valid,
                'warning_rows' => $warnings,
                'invalid_rows' => $invalid,
                'identity_conflict_rows' => $identityConflicts,
                'progress_percent' => 100,
                'validated_at' => now(),
                'finished_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);
            throw $exception;
        }
    }
}
