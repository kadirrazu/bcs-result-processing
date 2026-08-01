<?php

namespace App\Services\Registrations;

use App\Models\RegistrationImportBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Merge validated staging rows into the indexed registrations table in bulk. */
final class RegistrationApprovalService
{
    public function approve(int $batchId, int $approvedBy): RegistrationImportBatch
    {
        $batch = RegistrationImportBatch::query()->findOrFail($batchId);
        if (! in_array($batch->status, ['validated', 'approval_queued', 'failed'], true)) {
            throw new RuntimeException('Only a validated batch can be approved.');
        }

        /*
         * A failed approval may be retried only when no previous merge chunk was
         * committed. This prevents a partial batch from being replayed as a new
         * update run and keeps audit/rollback semantics deterministic.
         */
        if ($batch->status === 'failed' && (int) $batch->approved_rows > 0) {
            throw new RuntimeException('A partially approved batch cannot be retried automatically. Roll it back first.');
        }

        $batch->update([
            'status' => 'approving',
            'processed_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'heartbeat_at' => now(),
        ]);

        try {
            $eligible = DB::connection('exam')->table('registration_import_staging')
                ->where('batch_id', $batchId)
                ->whereIn('validation_status', ['valid', 'warning'])
                ->count();

            $processed = 0;
            $inserted = 0;
            $updated = 0;
            $chunkSize = max(500, (int) config('registrations.merge_chunk_size', 2000));

            DB::connection('exam')->table('registration_import_staging')
                ->where('batch_id', $batchId)
                ->whereIn('validation_status', ['valid', 'warning'])
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (
                    $batch, $batchId, &$processed, &$inserted, &$updated, $eligible
                ): void {
                    $regs = $rows->pluck('reg')->filter()->unique()->values()->all();
                    $existing = DB::connection('exam')->table('registrations')
                        ->whereIn('reg', $regs)
                        ->get()
                        ->map(static fn (object $row): array => (array) $row)
                        ->keyBy('reg');

                    $payload = [];
                    $timestamp = now()->format('Y-m-d H:i:s');
                    foreach ($rows as $row) {
                        $payload[] = [
                            'user_id' => $row->user_id,
                            'reg' => $row->reg,
                            'national_id' => $row->national_id,
                            'name' => $row->name,
                            'father_name' => $row->father_name,
                            'mother_name' => $row->mother_name,
                            'name_bn' => $row->name_bn,
                            'father_name_bn' => $row->father_name_bn,
                            'mother_name_bn' => $row->mother_name_bn,
                            'birth_date' => $row->birth_date,
                            'sex_code' => $row->sex_code,
                            'district_code' => $row->district_code,
                            'division_code' => $row->division_code,
                            'university_code' => $row->university_code,
                            'bachelor_subject_code' => $row->bachelor_subject_code,
                            'post_related_subject_code' => $row->post_related_subject_code,
                            'cadre_category' => $row->cadre_category,
                            'has_ff_quota' => $row->has_ff_quota,
                            'has_em_quota' => $row->has_em_quota,
                            'has_phc_quota' => $row->has_phc_quota,
                            'has_quota' => $row->has_quota,
                            'status' => $row->candidate_status,
                            'validation_status' => 'valid',
                            'comment' => $this->commentWithWarnings($row->comment, $row->validation_warnings),
                            'source_batch_id' => $batchId,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }

                    DB::connection('exam')->transaction(function () use (
                        $payload, $rows, $existing, $batchId, $timestamp, &$inserted, &$updated
                    ): void {
                        DB::connection('exam')->table('registrations')->upsert(
                            $payload,
                            ['reg'],
                            [
                                'user_id', 'national_id', 'name', 'father_name', 'mother_name',
                                'name_bn', 'father_name_bn', 'mother_name_bn', 'birth_date',
                                'sex_code', 'district_code', 'division_code', 'university_code',
                                'bachelor_subject_code', 'post_related_subject_code', 'cadre_category',
                                'has_ff_quota', 'has_em_quota', 'has_phc_quota', 'has_quota',
                                'status', 'validation_status', 'comment', 'source_batch_id', 'updated_at',
                            ],
                        );

                        $after = DB::connection('exam')->table('registrations')
                            ->whereIn('reg', array_column($payload, 'reg'))
                            ->get(['id', 'reg'])
                            ->keyBy('reg');

                        $audit = [];
                        $stagingUpdates = [];
                        foreach ($rows as $row) {
                            $registration = $after->get($row->reg);
                            if ($registration === null) {
                                throw new RuntimeException("Registration {$row->reg} was not found after merge.");
                            }

                            $before = $existing->get($row->reg);
                            $action = $before === null ? 'inserted' : 'updated';
                            $action === 'inserted' ? $inserted++ : $updated++;

                            $audit[] = [
                                'batch_id' => $batchId,
                                'source_row' => $row->source_row,
                                'registration_id' => $registration->id,
                                'reg' => $row->reg,
                                'user_id' => $row->user_id,
                                'action' => $action,
                                'warnings' => $row->validation_warnings,
                                'errors' => null,
                                'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
                                'after_data' => null,
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ];
                            $stagingUpdates[] = [
                                'id' => $row->id,
                                // Required insert columns must be present because MySQL compiles
                                // upsert as INSERT ... ON DUPLICATE KEY UPDATE.
                                'batch_id' => $row->batch_id,
                                'source_row' => $row->source_row,
                                'registration_id' => $registration->id,
                                'updated_at' => $timestamp,
                            ];
                        }

                        DB::connection('exam')->table('registration_import_rows')->insert($audit);
                        DB::connection('exam')->table('registration_import_staging')->upsert(
                            $stagingUpdates,
                            ['id'],
                            ['registration_id', 'updated_at'],
                        );
                    });

                    $processed += count($payload);
                    $batch->update([
                        'processed_rows' => $processed,
                        'approved_rows' => $processed,
                        'inserted_rows' => $inserted,
                        'updated_rows' => $updated,
                        'progress_percent' => $eligible > 0 ? min(100, round(($processed / $eligible) * 100, 4)) : 100,
                        'heartbeat_at' => now(),
                    ]);
                });

            $batch->update([
                'status' => 'approved',
                'processed_rows' => $processed,
                'approved_rows' => $processed,
                'inserted_rows' => $inserted,
                'updated_rows' => $updated,
                'progress_percent' => 100,
                'approved_at' => now(),
                'approved_by' => $approvedBy,
                'heartbeat_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                'heartbeat_at' => now(),
            ]);
            throw $exception;
        }
    }

    private function commentWithWarnings(?string $comment, ?string $warningsJson): ?string
    {
        $warnings = $warningsJson === null ? [] : (json_decode($warningsJson, true) ?: []);
        if ($warnings === []) {
            return $comment;
        }

        $parts = array_filter([trim((string) $comment)]);
        foreach ($warnings as $warning) {
            $parts[] = '[IMPORT WARNING] '.$warning;
        }

        return implode("\n", array_values(array_unique($parts)));
    }
}
