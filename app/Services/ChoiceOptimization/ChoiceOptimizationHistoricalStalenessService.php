<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationProcessingState;

final class ChoiceOptimizationHistoricalStalenessService
{
    public function markIfProduced(string $reason, ?int $actorId = null, array $context = []): void
    {
        $state = ChoiceOptimizationProcessingState::query()
            ->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        if (
            ! in_array((string) $state->status, [
                'historical_optimized',
                'historical_optimized_with_blocking',
                'finalized',
            ], true)
            && ! filled($state->dataset_hash)
        ) {
            return;
        }

        $from = (string) $state->status;

        $state->update([
            'is_stale' => true,
            'stale_reason' => $reason,
            'finalized_by' => null,
            'finalized_at' => null,
        ]);

        ChoiceOptimizationProcessingAudit::query()->create([
            'event' => 'HISTORICAL_CHOICE_OPTIMIZATION_STALE',
            'actor_id' => $actorId,
            'from_status' => $from,
            'to_status' => $from,
            'context' => array_merge([
                'reason' => $reason,
            ], $context),
            'created_at' => now(),
        ]);
    }
}
