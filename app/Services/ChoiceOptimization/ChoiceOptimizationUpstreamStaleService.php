<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationProcessingState;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use Throwable;

final class ChoiceOptimizationUpstreamStaleService
{
    public function __construct(
        private readonly ChoiceValidationFinalizedDatasetService $choices,
        private readonly ChoiceOptimizationHistoricalStalenessService $staleness,
    ) {}

    /**
     * Choice Optimization is derived from finalized Choice Validation.
     * If that authority is stale/missing, or its finalized hash/version no longer
     * matches the snapshot consumed by Optimization, the Optimization output must
     * immediately lose Allocation-ready authority.
     */
    public function synchronize(?int $actorId = null): ChoiceOptimizationProcessingState
    {
        $state = ChoiceOptimizationProcessingState::query()
            ->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        try {
            $choice = $this->choices->storedFinalizedSummary();
        } catch (Throwable $e) {
            $this->staleness->markIfProduced(
                'Choice Validation is not current/finalized. Re-run and finalize Choice Validation, then re-process Choice Optimization.',
                $actorId,
                ['dependency' => 'choice_validation']
            );

            return $state->refresh();
        }

        $snapshot = (array) $state->source_snapshot;
        $expectedHash = (string) ($snapshot['choice_validation_hash'] ?? '');
        $expectedVersion = (int) ($snapshot['choice_validation_version'] ?? 0);
        $currentHash = (string) ($choice['dataset_hash'] ?? '');
        $currentVersion = (int) ($choice['validation_version'] ?? 0);

        if (($expectedHash !== '' && ! hash_equals($expectedHash, $currentHash))
            || ($expectedVersion > 0 && $expectedVersion !== $currentVersion)) {
            $this->staleness->markIfProduced(
                sprintf(
                    'Choice Validation authority changed after Choice Optimization (Optimization used v%d; current finalized v%d). Re-process Choice Optimization.',
                    $expectedVersion,
                    $currentVersion,
                ),
                $actorId,
                [
                    'dependency' => 'choice_validation',
                    'optimization_choice_validation_version' => $expectedVersion,
                    'current_choice_validation_version' => $currentVersion,
                ]
            );
        }

        return $state->refresh();
    }
}
