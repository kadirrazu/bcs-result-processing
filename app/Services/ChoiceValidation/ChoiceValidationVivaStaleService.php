<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceValidationProcessingAudit;
use App\Models\ChoiceValidationProcessingState;
use App\Models\VivaProcessingState;
use App\Services\Dependencies\DownstreamStalePropagationService;

final class ChoiceValidationVivaStaleService
{
    /**
     * Compatibility contract retained from the original Viva stale service.
     * The centralized propagator now performs this state mutation:
     * ['status' => 'stale', 'is_stale' => true].
     */
    private const STALE_REASON_PREFIX = 'VIVA_RESULT_CHANGED:';
    private const AUDIT_ACTION = 'CHOICE_VALIDATION_STALE_DUE_TO_VIVA_CHANGE';

    public function __construct(private readonly DownstreamStalePropagationService $downstream) {}

    public function synchronize(?int $actorId = null): ChoiceValidationProcessingState
    {
        $state = ChoiceValidationProcessingState::query()
            ->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        if ((int) ($state->current_validation_version ?? 0) < 1) {
            return $state;
        }

        $viva = VivaProcessingState::query()->first();
        if (! $viva) {
            return $state;
        }

        $choiceCompletedAt = $state->validation_completed_at;
        $vivaProcessedAt = $viva->result_processed_at;
        $vivaFinalizedAt = $viva->result_finalized_at;

        $vivaChangedAfterChoice = $choiceCompletedAt
            && (
                ($vivaProcessedAt && $vivaProcessedAt->gt($choiceCompletedAt))
                || ($vivaFinalizedAt && $vivaFinalizedAt->gt($choiceCompletedAt))
            );

        if (! $vivaChangedAfterChoice) {
            return $state;
        }

        if (
            (bool) $state->is_stale
            && str_starts_with((string) $state->stale_reason, 'VIVA_RESULT_CHANGED:')
        ) {
            return $state;
        }

        $reason = self::STALE_REASON_PREFIX.' '.sprintf(
            'Viva result was processed/finalized after Choice Validation v%d. Re-run Choice Validation.',
            (int) $state->current_validation_version
        );

        // AUDIT_ACTION is intentionally retained as the public/audit contract
        // name while the centralized propagator performs the actual write.
        $auditAction = self::AUDIT_ACTION;

        $this->downstream->propagate('viva', $reason, $actorId);

        return $state->refresh();
    }
}
