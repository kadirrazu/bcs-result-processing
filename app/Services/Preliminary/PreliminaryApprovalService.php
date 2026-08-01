<?php

namespace App\Services\Preliminary;

use App\Enums\PreliminaryProcessingStatus;
use App\Models\PreliminaryImportBatch;
use App\Models\PreliminaryProcessingState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Bulk-merges valid/warning staging rows into preliminary_results. */
final class PreliminaryApprovalService
{
    public function approve(int $batchId, int $approvedBy): PreliminaryImportBatch
    {
        $batch = PreliminaryImportBatch::query()->findOrFail($batchId);
        if (! in_array($batch->status, ['validated', 'approval_queued', 'failed'], true)) {
            throw new RuntimeException('Only a validated preliminary batch can be approved.');
        }
        if ($batch->status === 'failed' && (int) $batch->approved_rows > 0) {
            throw new RuntimeException('A partially approved preliminary batch cannot be retried automatically.');
        }

        $batch->update([
            'status' => 'approving',
            'processed_rows' => 0,
            'approved_rows' => 0,
            'inserted_rows' => 0,
            'updated_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
        ]);

        try {
            $eligible = DB::connection('exam')->table('preliminary_import_staging')
                ->where('batch_id', $batchId)
                ->whereIn('validation_status', ['valid', 'warning'])
                ->count();

            $processed = $inserted = $updated = 0;
            $chunkSize = max(500, (int) config('preliminary.merge_chunk_size', 3000));

            DB::connection('exam')->table('preliminary_import_staging')
                ->where('batch_id', $batchId)
                ->whereIn('validation_status', ['valid', 'warning'])
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use ($batch, $batchId, $eligible, &$processed, &$inserted, &$updated): void {
                    $registrationIds = $rows->pluck('registration_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
                    $existing = DB::connection('exam')->table('preliminary_results')
                        ->whereIn('registration_id', $registrationIds)
                        ->get(['registration_id'])
                        ->keyBy('registration_id');

                    $payload = [];
                    $timestamp = now()->format('Y-m-d H:i:s');
                    foreach ($rows as $row) {
                        $payload[] = [
                            'registration_id' => $row->registration_id,
                            'user_id' => $row->user_id,
                            'reg' => $row->reg,
                            'mark' => $row->mark,
                            'raw_candidate_status' => $row->raw_candidate_status,
                            'candidate_status' => $row->candidate_status,
                            'result_status' => $row->candidate_status === 'cancelled' ? 'cancelled' : null,
                            'applied_cutoff_mark' => null,
                            'validation_status' => $row->validation_status,
                            'source_batch_id' => $batchId,
                            'finalized_at' => null,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];

                        isset($existing[(int) $row->registration_id]) ? $updated++ : $inserted++;
                    }

                    DB::connection('exam')->transaction(function () use ($payload): void {
                        DB::connection('exam')->table('preliminary_results')->upsert(
                            $payload,
                            ['registration_id'],
                            [
                                'user_id', 'reg', 'mark', 'raw_candidate_status', 'candidate_status',
                                'result_status', 'applied_cutoff_mark', 'validation_status',
                                'source_batch_id', 'finalized_at', 'updated_at',
                            ],
                        );
                    });

                    $processed += count($payload);
                    $batch->update([
                        'processed_rows' => $processed,
                        'approved_rows' => $processed,
                        'inserted_rows' => $inserted,
                        'updated_rows' => $updated,
                        'progress_percent' => $eligible > 0 ? min(100, round(($processed / $eligible) * 100, 4)) : 100,
                    ]);
                });

            // The approved file is a full preliminary snapshot. Rows left from an older
            // approved batch represent candidates absent from the new source and must not
            // remain in preliminary_results. We remove them only after every new chunk
            // has merged successfully, so an early job failure leaves the old snapshot intact.
            DB::connection('exam')->table('preliminary_results')
                ->where('source_batch_id', '!=', $batchId)
                ->delete();

            $batch->update([
                'status' => 'approved',
                'processed_rows' => $processed,
                'approved_rows' => $processed,
                'inserted_rows' => $inserted,
                'updated_rows' => $updated,
                'progress_percent' => 100,
                'approved_at' => now(),
                'approved_by' => $approvedBy,
                'finished_at' => now(),
            ]);

            PreliminaryProcessingState::query()->updateOrCreate(
                ['id' => 1],
                [
                    'status' => PreliminaryProcessingStatus::MarkImported->value,
                    'latest_import_batch_id' => $batchId,
                    // A new approved mark import invalidates downstream processing facts.
                    'cutoff_mark' => null,
                    'cutoff_set_by' => null,
                    'cutoff_set_at' => null,
                    'cutoff_requires_review' => false,
                    'current_cutoff_decision_id' => null,
                    'latest_finalization_run_id' => null,
                    'latest_reconciliation_report_id' => null,
                    'reconciliation_generated_by' => null,
                    'reconciliation_generated_at' => null,
                    'latest_distribution_report_id' => null,
                    'distribution_generated_by' => null,
                    'distribution_generated_at' => null,
                    'result_finalized_by' => null,
                    'result_finalized_at' => null,
                    'summary' => null,
                ],
            );

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
