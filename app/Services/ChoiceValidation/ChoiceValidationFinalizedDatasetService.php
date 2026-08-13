<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceValidationFinalizationRun;
use App\Models\ChoiceValidationProcessingState;
use App\Models\ChoiceValidationResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class ChoiceValidationFinalizedDatasetService
{
    public function __construct(
        private readonly ChoiceValidationDatasetHasher $hasher,
    ) {}

    public function state(): ChoiceValidationProcessingState
    {
        return ChoiceValidationProcessingState::query()
            ->firstOrCreate(['id' => 1], ['status' => 'not_started']);
    }

    public function finalizedVersion(): int
    {
        $this->assertReady();

        return (int) $this->state()->finalized_validation_version;
    }

    public function finalizationRun(): ChoiceValidationFinalizationRun
    {
        return $this->verifiedFinalizationRun();
    }

    /**
     * Verify the finalized dataset hash exactly once and return the
     * authoritative finalization record. Export/report code should use this
     * entry point instead of chaining summary() + finalizedVersion() + results(),
     * which would otherwise repeat the full dataset hash scan.
     */
    public function verifiedFinalizationRun(): ChoiceValidationFinalizationRun
    {
        $state = $this->assertReady();

        return ChoiceValidationFinalizationRun::query()
            ->findOrFail($state->latest_finalization_run_id);
    }

    /**
     * Lightweight finalized metadata for operator/read-only pages.
     * Does not re-hash Choice Validation rows. Strict downstream work must use
     * verifiedSummary().
     *
     * @return array<string,mixed>
     */
    public function storedFinalizedSummary(): array
    {
        $state = $this->state();

        if (! $this->isReady($state)) {
            throw ValidationException::withMessages([
                'choice_validation' => 'A current, non-stale finalized Choice Validation dataset is required.',
            ]);
        }

        $run = ChoiceValidationFinalizationRun::query()->find($state->latest_finalization_run_id);
        if (! $run || ! $run->dataset_hash) {
            throw ValidationException::withMessages([
                'choice_validation' => 'Choice Validation finalized hash metadata could not be resolved.',
            ]);
        }

        return $this->summaryFromRun($run);
    }

    /** @return array<string,mixed> */
    public function verifiedSummary(): array
    {
        $run = $this->verifiedFinalizationRun();

        return $this->summaryFromRun($run);
    }

    /** @return Collection<int,ChoiceValidationResult> */
    public function results(): Collection
    {
        $version = $this->finalizedVersion();

        return ChoiceValidationResult::query()
            ->with(['registration', 'source.items'])
            ->where('validation_version', $version)
            ->orderBy('reg')
            ->orderBy('id')
            ->get();
    }

    /** Candidates with at least one finalized validated Choice. */
    public function choiceReadyResults(): Collection
    {
        $version = $this->finalizedVersion();

        return ChoiceValidationResult::query()
            ->with(['registration', 'source.items'])
            ->where('validation_version', $version)
            ->where('status', 'valid')
            ->where('validated_choice_count', '>', 0)
            ->orderBy('reg')
            ->orderBy('id')
            ->get();
    }

    /** @return array<int,array<int,string>> keyed by registration_id */
    public function validatedChoiceMap(): array
    {
        return $this->choiceReadyResults()
            ->mapWithKeys(fn (ChoiceValidationResult $row): array => [
                (int) $row->registration_id => array_values((array) $row->validated_choice_codes),
            ])
            ->all();
    }

    /** @return array<string,mixed> */
    public function summary(): array
    {
        $state = $this->state();

        if (! $this->isReady($state)) {
            return [
                'ready' => false,
                'validation_version' => null,
                'source_version' => null,
                'circular_version' => null,
                'total_candidates' => 0,
                'valid_candidates' => 0,
                'not_applicable_candidates' => 0,
                'zero_valid_choice_candidates' => 0,
                'kept_choices' => 0,
                'removed_choices' => 0,
                'expanded_choices' => 0,
                'dataset_hash' => null,
                'finalized_at' => null,
                'finalized_by_name' => null,
                'finalization_note' => null,
            ];
        }

        $run = ChoiceValidationFinalizationRun::query()
            ->findOrFail($state->latest_finalization_run_id);

        return $this->summaryFromRun($run);
    }

    /** @return array<string,mixed> */
    private function summaryFromRun(ChoiceValidationFinalizationRun $run): array
    {
        return [
            'ready' => true,
            'validation_version' => (int) $run->validation_version,
            'source_version' => (int) $run->source_version,
            'circular_version' => (int) $run->circular_version,
            'total_candidates' => (int) $run->total_candidates,
            'valid_candidates' => (int) $run->valid_candidates,
            'not_applicable_candidates' => (int) $run->not_applicable_candidates,
            'zero_valid_choice_candidates' => (int) $run->zero_valid_choice_candidates,
            'kept_choices' => (int) $run->kept_choices,
            'removed_choices' => (int) $run->removed_choices,
            'expanded_choices' => (int) $run->expanded_choices,
            'dataset_hash' => (string) $run->dataset_hash,
            'finalized_at' => $run->finalized_at,
            'finalized_by_name' => $run->finalized_by_name,
            'finalization_note' => $run->finalization_note,
        ];
    }

    private function assertReady(): ChoiceValidationProcessingState
    {
        $state = $this->state();

        if (! $this->isReady($state)) {
            throw ValidationException::withMessages([
                'choice_validation' => 'A current, non-stale finalized Choice Validation dataset is required.',
            ]);
        }

        $run = ChoiceValidationFinalizationRun::query()
            ->find($state->latest_finalization_run_id);

        if (! $run) {
            throw ValidationException::withMessages([
                'choice_validation' => 'Choice Validation finalization record could not be resolved.',
            ]);
        }

        $currentHash = $this->hasher->hash((int) $state->finalized_validation_version);

        if (! hash_equals((string) $run->dataset_hash, $currentHash)) {
            throw ValidationException::withMessages([
                'choice_validation' => 'Finalized Choice Validation dataset hash mismatch detected. Revalidate and finalize again.',
            ]);
        }

        return $state;
    }

    private function isReady(ChoiceValidationProcessingState $state): bool
    {
        return (string) $state->status === 'finalized'
            && ! (bool) $state->is_stale
            && (int) ($state->finalized_validation_version ?? 0) > 0
            && (int) $state->finalized_validation_version === (int) $state->current_validation_version
            && (int) ($state->latest_finalization_run_id ?? 0) > 0;
    }
}
