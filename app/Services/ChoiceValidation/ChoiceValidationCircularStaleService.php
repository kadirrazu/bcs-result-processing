<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceValidationProcessingState;
use App\Models\ChoiceValidationRun;
use App\Services\Circular\CircularFinalizedDatasetService;
use App\Services\Dependencies\DownstreamStalePropagationService;
use Throwable;

final class ChoiceValidationCircularStaleService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly DownstreamStalePropagationService $downstream,
    ) {}

    public function synchronize(?int $actorId = null): ChoiceValidationProcessingState
    {
        $state = ChoiceValidationProcessingState::query()
            ->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        if ((int) ($state->current_validation_version ?? 0) < 1) {
            return $state;
        }

        $run = $state->latest_validation_run_id
            ? ChoiceValidationRun::query()->find($state->latest_validation_run_id)
            : ChoiceValidationRun::query()
                ->where('validation_version', (int) $state->current_validation_version)
                ->latest('id')
                ->first();

        if (! $run) {
            return $state;
        }

        try {
            $currentCircularVersion = $this->circular->finalizedVersion();
        } catch (Throwable $e) {
            $this->downstream->propagate(
                'circular',
                'Current Circular is no longer finalized/ready for Choice Validation. '.$e->getMessage(),
                $actorId,
            );

            return $state->refresh();
        }

        if ((int) $run->circular_version !== (int) $currentCircularVersion) {
            $this->downstream->propagate(
                'circular',
                sprintf(
                    'Choice Validation v%d used Circular v%d, but current finalized Circular is v%d. Re-run Choice Validation.',
                    (int) $run->validation_version,
                    (int) $run->circular_version,
                    (int) $currentCircularVersion,
                ),
                $actorId,
            );
        }

        return $state->refresh();
    }
}
