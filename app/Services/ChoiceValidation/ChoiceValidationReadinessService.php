<?php

namespace App\Services\ChoiceValidation;

use App\Enums\CircularProcessingStatus;
use App\Enums\VivaProcessingStatus;
use App\Models\ChoiceSource;
use App\Models\ChoiceValidationProcessingState;
use App\Models\CircularProcessingState;
use App\Models\VivaFinalizationRun;
use App\Models\VivaProcessingState;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Validation\ValidationException;

final class ChoiceValidationReadinessService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceValidationVivaStaleService $vivaStale,
        private readonly ChoiceValidationCircularStaleService $circularStale,
    ) {}

    /**
     * @return array{
     *   ready:bool,
     *   checks:array<int,array{key:string,label:string,ready:bool,status:string,detail:string}>,
     *   reasons:list<string>
     * }
     */
    public function summary(): array
    {
        // Synchronize already-generated Choice Validation before presenting
        // upstream readiness. These calls are no-ops when validation has not run.
        $this->vivaStale->synchronize();
        $this->circularStale->synchronize();

        $checks = [];

        $circularState = CircularProcessingState::query()->first();
        $circularReady = false;
        $circularDetail = 'Circular has not been finalized.';

        if ($circularState) {
            $version = (int) ($circularState->finalized_version ?? 0);
            $circularReady = $circularState->status === CircularProcessingStatus::Finalized
                && ! (bool) $circularState->is_stale
                && $version > 0
                && (int) $circularState->current_version === $version
                && (int) $circularState->approved_version === $version
                && (int) $circularState->confirmed_version === $version;

            $circularDetail = $circularReady
                ? 'Finalized Circular v'.$version.' is current, confirmed and non-stale.'
                : $this->circularNotReadyReason($circularState);
        }

        // Hash verification is part of the actual downstream contract. Do it here
        // too so the operator never sees READY for a dataset that processChoices()
        // would reject immediately.
        if ($circularReady) {
            try {
                $summary = $this->circular->verifiedSummary();
                $circularDetail = 'Finalized Circular v'.(int) $summary['version'].' is ready; dataset hash verified.';
            } catch (ValidationException $e) {
                $circularReady = false;
                $circularDetail = $e->validator->errors()->first('circular')
                    ?: 'Finalized Circular dataset verification failed.';
            }
        }

        $checks[] = [
            'key' => 'circular',
            'label' => 'Circular',
            'ready' => $circularReady,
            'status' => $circularReady ? 'READY' : 'NOT_READY',
            'detail' => $circularDetail,
        ];

        $vivaState = VivaProcessingState::query()->first();
        $vivaFinalization = VivaFinalizationRun::query()
            ->where('status', 'current')
            ->latest('id')
            ->first();

        $vivaReady = $vivaState
            && $vivaState->status === VivaProcessingStatus::ResultFinalized
            && ! (bool) $vivaState->is_stale
            && $vivaState->result_finalized_at
            && $vivaFinalization
            && $vivaFinalization->processing_run_id
            && $vivaFinalization->finalized_at;

        $checks[] = [
            'key' => 'viva',
            'label' => 'Viva Result',
            'ready' => (bool) $vivaReady,
            'status' => $vivaReady ? 'READY' : 'NOT_READY',
            'detail' => $vivaReady
                ? 'Current Viva result is finalized, non-stale and its finalized processing run is resolved.'
                : $this->vivaNotReadyReason($vivaState, $vivaFinalization),
        ];

        $choiceState = ChoiceValidationProcessingState::query()->first();
        $sourceVersion = (int) ($choiceState?->approved_source_version ?? 0);
        $sourceCount = $sourceVersion > 0
            ? ChoiceSource::query()->where('source_version', $sourceVersion)->count()
            : 0;
        $sourceReady = $sourceVersion > 0 && $sourceCount > 0;

        $checks[] = [
            'key' => 'source',
            'label' => 'Approved Choice Source',
            'ready' => $sourceReady,
            'status' => $sourceReady ? 'READY' : 'NOT_READY',
            'detail' => $sourceReady
                ? 'Approved Choice source v'.$sourceVersion.' contains '.number_format($sourceCount).' candidate row(s).'
                : 'Approve at least one valid Choice source dataset before running validation.',
        ];

        $reasons = array_values(array_map(
            static fn (array $check): string => $check['label'].': '.$check['detail'],
            array_filter($checks, static fn (array $check): bool => ! $check['ready'])
        ));

        return [
            'ready' => $reasons === [],
            'checks' => $checks,
            'reasons' => $reasons,
        ];
    }

    public function assertReady(): void
    {
        $summary = $this->summary();

        if (! $summary['ready']) {
            throw ValidationException::withMessages([
                'validation' => implode(' ', $summary['reasons']),
            ]);
        }
    }

    private function circularNotReadyReason(CircularProcessingState $state): string
    {
        if ((bool) $state->is_stale) {
            return 'Circular is stale'.($state->stale_reason ? ': '.$state->stale_reason : '.');
        }

        if ($state->status !== CircularProcessingStatus::Finalized) {
            return 'Circular is not finalized. Current status: '.$this->enumValue($state->status).'.';
        }

        return 'Circular finalized/current/approved/confirmed versions are not aligned.';
    }

    private function vivaNotReadyReason(?VivaProcessingState $state, mixed $finalization): string
    {
        if (! $state) {
            return 'Viva processing has not started.';
        }

        if ((bool) $state->is_stale) {
            return 'Viva result is stale'.($state->stale_reason ? ': '.$state->stale_reason : '.');
        }

        if ($state->status !== VivaProcessingStatus::ResultFinalized || ! $state->result_finalized_at) {
            return 'Viva result is not finalized. Current status: '.$this->enumValue($state->status).'. Finalize the current Viva result first.';
        }

        if (! $finalization || ! $finalization->processing_run_id || ! $finalization->finalized_at) {
            return 'Current Viva finalization record or finalized processing run could not be resolved.';
        }

        return 'Viva result is not ready for Choice Validation.';
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return strtoupper((string) $value->value);
        }

        return strtoupper((string) ($value ?: 'UNKNOWN'));
    }
}
