<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationGoogleFormBatch;
use App\Models\ChoiceOptimizationGoogleFormRecommendation;
use App\Models\ChoiceOptimizationGoogleFormRow;
use Illuminate\Support\Facades\DB;

final class ChoiceOptimizationGoogleFormMergeService
{
    public function __construct(private readonly ChoiceOptimizationHistoricalStalenessService $staleness) {}

    public function mergeValid(int $batchId, int $actorId): ChoiceOptimizationGoogleFormBatch
    {
        $batch = ChoiceOptimizationGoogleFormBatch::query()->findOrFail($batchId);
        abort_unless(in_array((string) $batch->status, ['validated', 'merge_queued'], true), 409, 'Only a validated Google Form batch can be merged.');

        $target = ChoiceOptimizationGoogleFormRow::query()
            ->where('batch_id', $batchId)
            ->where('validation_status', 'valid')
            ->where('merge_status', 'pending')
            ->count();

        $alreadyMerged = ChoiceOptimizationGoogleFormRow::query()
            ->where('batch_id', $batchId)
            ->where('merge_status', 'merged')
            ->count();

        $batch->update([
            'status' => 'merging',
            'processed_rows' => 0,
            'merged_rows' => $alreadyMerged,
            'progress_percent' => $target > 0 ? 0 : 100,
            'failure_message' => null,
            'finished_at' => null,
        ]);

        $mergedThisRun = 0;

        ChoiceOptimizationGoogleFormRow::query()
            ->where('batch_id', $batchId)
            ->where('validation_status', 'valid')
            ->where('merge_status', 'pending')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($batch, $actorId, $target, $alreadyMerged, &$mergedThisRun): void {
                DB::connection('exam')->transaction(function () use ($rows, $batch, $actorId, &$mergedThisRun): void {
                    foreach ($rows as $row) {
                        // A newer valid Google Form row supersedes the previously accepted
                        // recommendation for the same current candidate + previous BCS.
                        // Unrelated accepted recommendations are left untouched so targeted
                        // correction/re-upload batches remain safe.
                        $rec = ChoiceOptimizationGoogleFormRecommendation::query()->updateOrCreate(
                            [
                                'registration_id' => $row->registration_id,
                                'previous_bcs_number' => $row->previous_bcs_number,
                            ],
                            [
                                'current_reg' => $row->current_reg,
                                'cadre' => $row->cadre,
                                'source_batch_id' => $batch->id,
                                'source_row_id' => $row->id,
                                'accepted_by' => $actorId,
                                'accepted_at' => now(),
                            ]
                        );

                        $row->update([
                            'merge_status' => 'merged',
                            'merged_recommendation_id' => $rec->id,
                            'merged_at' => now(),
                        ]);
                        $mergedThisRun++;
                    }
                });

                // Update outside the chunk transaction so the status endpoint can see it immediately.
                $batch->update([
                    'processed_rows' => $mergedThisRun,
                    'merged_rows' => $alreadyMerged + $mergedThisRun,
                    'progress_percent' => $target > 0 ? round(min(100, ($mergedThisRun / $target) * 100), 2) : 100,
                ]);
            });

        $totalMerged = ChoiceOptimizationGoogleFormRow::query()
            ->where('batch_id', $batchId)
            ->where('merge_status', 'merged')
            ->count();

        $batch->update([
            'status' => (int) $batch->invalid_rows > 0 ? 'partially_merged' : 'merged',
            'processed_rows' => $mergedThisRun,
            'merged_rows' => $totalMerged,
            'progress_percent' => 100,
            'merged_by' => $actorId,
            'merged_at' => now(),
            'finished_at' => now(),
        ]);

        if ($mergedThisRun > 0) {
            $this->staleness->markIfProduced(
                'Accepted Google Form historical recommendations changed. Historical Choice Optimization must be re-processed.',
                $actorId,
                ['google_form_batch_id' => $batchId, 'merged_rows' => $mergedThisRun]
            );
        }

        return $batch->refresh();
    }
}
