<?php

namespace App\Services\Dependencies;

use App\Models\ChoiceValidationProcessingAudit;
use App\Models\ChoiceValidationProcessingState;
use App\Models\MeritProcessingState;
use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationProcessingState;
use App\Models\TabulationFinalizationRun;
use App\Models\TabulationProcessingState;
use Illuminate\Support\Facades\Schema;

final class DownstreamStalePropagationService
{
    /**
     * Canonical dependency graph.
     *
     * IMPORTANT: Circular has no Tabulation dependency.
     *
     * @return list<string>
     */
    public function downstreamFor(string $upstream): array
    {
        return match (strtolower(trim($upstream))) {
            'registration', 'preliminary', 'written' => ['tabulation', 'merit'],
            'viva' => ['tabulation', 'choice_validation', 'merit'],
            'circular' => ['choice_validation', 'choice_optimization', 'merit'],
            'choice_validation' => ['choice_optimization', 'merit'],
            'tabulation' => ['merit'],
            default => [],
        };
    }

    /**
     * @return array<string,bool>
     */
    public function propagate(
        string $upstream,
        string $reason,
        ?int $actorId = null,
    ): array {
        $result = [];

        foreach ($this->downstreamFor($upstream) as $downstream) {
            $result[$downstream] = match ($downstream) {
                'tabulation' => $this->markTabulation($upstream, $reason),
                'choice_validation' => $this->markChoiceValidation($upstream, $reason, $actorId),
                'merit' => $this->markMerit($upstream, $reason),
                'choice_optimization' => $this->markChoiceOptimization($upstream, $reason, $actorId),
                default => false,
            };
        }

        return $result;
    }

    public function markChoiceValidation(
        string $upstream,
        string $reason,
        ?int $actorId = null,
    ): bool {
        if (! Schema::connection('exam')->hasTable('choice_validation_processing_states')) {
            return false;
        }

        $state = ChoiceValidationProcessingState::query()->first();
        if (! $state || (int) ($state->current_validation_version ?? 0) < 1) {
            return false;
        }

        $token = strtoupper($upstream).'_UPSTREAM_CHANGED: '.$reason;

        if ((bool) $state->is_stale && (string) $state->stale_reason === $token) {
            return false;
        }

        $state->update([
            'status' => 'stale',
            'is_stale' => true,
            'stale_reason' => $token,
        ]);

        if (Schema::connection('exam')->hasTable('choice_validation_processing_audits')) {
            $action = strtolower($upstream) === 'viva'
                ? 'CHOICE_VALIDATION_STALE_DUE_TO_VIVA_CHANGE'
                : 'CHOICE_VALIDATION_STALE_DUE_TO_'.strtoupper($upstream);

            ChoiceValidationProcessingAudit::query()->create([
                'action' => $action,
                'actor_id' => $actorId,
                'actor_name' => null,
                'reason' => $token,
                'summary' => [
                    'upstream' => strtoupper($upstream),
                    'validation_version' => (int) $state->current_validation_version,
                    'propagated_at' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        }

        return true;
    }

    public function markTabulation(string $upstream, string $reason): bool
    {
        if (! Schema::connection('exam')->hasTable('tabulation_processing_states')) {
            return false;
        }

        $state = TabulationProcessingState::query()->first();
        if (! $state || ! $state->latest_run_id) {
            return false;
        }

        $token = strtoupper($upstream).'_UPSTREAM_CHANGED: '.$reason;

        if ((bool) $state->is_stale && (string) $state->stale_reason === $token) {
            return false;
        }

        $state->update([
            'status' => 'stale',
            'is_stale' => true,
            'stale_reason' => $token,
            'finalized_at' => null,
            'finalized_by' => null,
        ]);

        if (Schema::connection('exam')->hasTable('tabulation_finalization_runs')) {
            TabulationFinalizationRun::query()
                ->where('status', 'current')
                ->update(['status' => 'superseded']);
        }

        return true;
    }

    public function markChoiceOptimization(string $upstream, string $reason, ?int $actorId = null): bool
    {
        if (! Schema::connection('exam')->hasTable('choice_optimization_processing_states')) {
            return false;
        }

        $state = ChoiceOptimizationProcessingState::query()->first();
        if (! $state) {
            return false;
        }

        // Do not manufacture stale state before Optimization has produced anything.
        if (! in_array((string) $state->status, ['historical_optimized', 'historical_optimized_with_blocking', 'finalized'], true)
            && ! filled($state->dataset_hash)) {
            return false;
        }

        $token = 'CHOICE_OPTIMIZATION_UPSTREAM_CHANGED_'.strtoupper($upstream).': '.$reason;
        if ((bool) $state->is_stale && (string) $state->stale_reason === $token) {
            return false;
        }

        $from = (string) $state->status;
        $state->update([
            'is_stale' => true,
            'stale_reason' => $token,
            'finalized_by' => null,
            'finalized_at' => null,
        ]);

        if (Schema::connection('exam')->hasTable('choice_optimization_processing_audits')) {
            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => 'CHOICE_OPTIMIZATION_STALE_DUE_TO_'.strtoupper($upstream),
                'actor_id' => $actorId,
                'from_status' => $from,
                'to_status' => $from,
                'context' => [
                    'upstream' => strtoupper($upstream),
                    'reason' => $token,
                    'propagated_at' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        }

        return true;
    }

    public function markMerit(string $upstream, string $reason): bool
    {
        if (! Schema::connection('exam')->hasTable('merit_processing_states')) {
            return false;
        }

        $state = MeritProcessingState::query()->first();
        if (! $state || ! $state->latest_run_id) {
            return false;
        }

        $token = 'MERIT_UPSTREAM_CHANGED_'.strtoupper($upstream).': '.$reason;

        if ((bool) $state->is_stale && (string) $state->stale_reason === $token) {
            return false;
        }

        $state->update([
            'status' => 'stale',
            'is_stale' => true,
            'stale_reason' => $token,
        ]);

        return true;
    }
}
