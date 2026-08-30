<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\ChoiceOptimizationHistoricalMatch;
use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationProcessingState;
use App\Services\Circular\CircularFinalizedDatasetService;
use RuntimeException;

final class ChoiceOptimizationHistoricalChoiceFinalizationService
{
    public function __construct(
        private readonly ChoiceOptimizationHistoricalInputService $input,
        private readonly ChoiceOptimizationHistoricalChoiceService $historical,
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceOptimizationConsolidatedHistoricalRecommendationService $consolidated,
    ) {}

    public function finalize(int $actorId): ChoiceOptimizationProcessingState
    {
        $state = ChoiceOptimizationProcessingState::query()
            ->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        $from = (string) $state->status;
        $expectedOutputHash = (string) ($state->dataset_hash ?? '');
        $actualOutputHash = null;

        try {
            if (
                ! in_array((string) $state->status, ['historical_optimized', 'finalization_queued'], true)
                || (bool) $state->is_stale
            ) {
                throw new RuntimeException(
                    'Historical Choice Optimization must be current, complete, and free of blocking issues before finalization.'
                );
            }

            $state->update([
                'status' => 'finalizing',
                'is_stale' => false,
                'stale_reason' => null,
                'finalized_by' => null,
                'finalized_at' => null,
            ]);

            if (
                ChoiceOptimizationHistoricalMatch::query()
                    ->where('match_status', 'review')
                    ->where('resolution_status', 'pending')
                    ->exists()
            ) {
                throw new RuntimeException('Resolve all Historical Match REVIEW items before finalization.');
            }

            if (
                ChoiceOptimizationHistoricalChoice::query()
                    ->whereNotNull('blocking_issues')
                    ->exists()
            ) {
                throw new RuntimeException('Resolve every blocking historical cadre mapping issue before finalization.');
            }

            $snapshot = (array) $state->source_snapshot;
            $input = $this->input->snapshot();
            $circular = $this->circular->verifiedSummary();
            $historicalHash = $this->historical->historicalSnapshotHash();
            $googleFormHash = $this->consolidated->googleFormSnapshotHash();
            $consolidatedHash = $this->consolidated->snapshotHash();
            $outputHash = $this->historical->outputHashFromDatabase();
            $actualOutputHash = $outputHash;

            $checks = [
                'input_choice_hash' => (string) $input['source_hash'],
                'choice_validation_hash' => (string) $input['choice_validation_hash'],
                'circular_hash' => (string) ($circular['dataset_hash'] ?? ''),
                'historical_snapshot_hash' => $historicalHash,
                'google_form_snapshot_hash' => $googleFormHash,
                'consolidated_historical_hash' => $consolidatedHash,
            ];

            foreach ($checks as $key => $actual) {
                $expected = (string) ($snapshot[$key] ?? '');

                if ($expected === '' || ! hash_equals($expected, $actual)) {
                    throw new RuntimeException(
                        "Historical Choice Optimization source {$key} changed after processing. Re-process before finalization."
                    );
                }
            }

            if (! $state->dataset_hash || ! hash_equals((string) $state->dataset_hash, $outputHash)) {
                throw new RuntimeException(
                    'Historical Choice Optimization output hash changed after processing. Re-process before finalization.'
                );
            }

            $state->update([
                'status' => 'finalized',
                'is_stale' => false,
                'stale_reason' => null,
                'finalized_by' => $actorId,
                'finalized_at' => now(),
            ]);

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => 'CHOICE_OPTIMIZATION_FINALIZED',
                'actor_id' => $actorId,
                'from_status' => $from,
                'to_status' => 'finalized',
                'context' => [
                    'dataset_hash' => $outputHash,
                    'source_snapshot' => $snapshot,
                    'summary' => $state->summary,
                ],
                'created_at' => now(),
            ]);

            return $state->refresh();
        } catch (\Throwable $e) {
            $state->update([
                'status' => 'finalization_failed',
                'is_stale' => true,
                'stale_reason' => $e->getMessage(),
                'finalized_by' => null,
                'finalized_at' => null,
            ]);

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => 'CHOICE_OPTIMIZATION_FINALIZATION_FAILED',
                'actor_id' => $actorId,
                'from_status' => $from,
                'to_status' => 'finalization_failed',
                'context' => [
                    'message' => mb_substr($e->getMessage(), 0, 2000),
                    'expected_output_hash' => $expectedOutputHash !== '' ? $expectedOutputHash : null,
                    'actual_output_hash' => $actualOutputHash,
                ],
                'created_at' => now(),
            ]);

            throw $e;
        }
    }
}
