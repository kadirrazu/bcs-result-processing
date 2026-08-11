<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceSource;
use App\Models\ChoiceValidationFinalizationRun;
use App\Models\ChoiceValidationImportBatch;
use App\Models\ChoiceValidationManualCorrection;
use App\Models\ChoiceValidationProcessingAudit;
use App\Models\ChoiceValidationProcessingState;
use App\Models\ChoiceValidationResult;
use App\Models\ChoiceValidationRun;
use App\Models\User;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ChoiceValidationFinalizationService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceValidationDatasetHasher $hasher,
    ) {}

    /**
     * @return array{
     *   ready:bool,reasons:array<int,string>,state:ChoiceValidationProcessingState,
     *   run:?ChoiceValidationRun,source_batch:?ChoiceValidationImportBatch,
     *   result_count:int,source_count:int,pending_manual_corrections:int,
     *   current_circular_version:?int
     * }
     */
    public function readiness(): array
    {
        $state = ChoiceValidationProcessingState::query()
            ->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        $reasons = [];
        $run = $state->latest_validation_run_id
            ? ChoiceValidationRun::query()->find($state->latest_validation_run_id)
            : null;

        $sourceBatch = null;
        if ($state->approved_source_version) {
            $sourceBatch = ChoiceValidationImportBatch::query()
                ->where('source_version', $state->approved_source_version)
                ->latest('id')
                ->first();
        }

        $sourceCount = $state->approved_source_version
            ? ChoiceSource::query()
                ->where('source_version', $state->approved_source_version)
                ->count()
            : 0;

        $resultCount = $state->current_validation_version
            ? ChoiceValidationResult::query()
                ->where('validation_version', $state->current_validation_version)
                ->count()
            : 0;

        $pendingManualCorrections = ChoiceValidationManualCorrection::query()
            ->when(
                $state->approved_source_version,
                fn ($query) => $query->where('source_version', $state->approved_source_version)
            )
            ->when(
                $state->current_validation_version,
                fn ($query) => $query->where('validation_version', $state->current_validation_version)
            )
            ->whereNull('revalidated_at')
            ->count();

        $currentCircularVersion = null;
        try {
            $currentCircularVersion = (int) $this->circular->finalizedVersion();
        } catch (Throwable) {
            $reasons[] = 'A finalized Circular is required.';
        }

        if (! $state->approved_source_version) {
            $reasons[] = 'Approve a Choice source dataset first.';
        }

        if (! $sourceBatch) {
            $reasons[] = 'The approved Choice source batch could not be resolved.';
        } elseif (
            $sourceBatch->status !== 'approved'
            || (int) $sourceBatch->invalid_rows !== 0
        ) {
            $reasons[] = 'All invalid Choice source rows must be corrected and approved before finalization.';
        }

        if ($sourceCount < 1) {
            $reasons[] = 'The approved Choice source dataset is empty.';
        }

        if ((int) $state->current_validation_version < 1) {
            $reasons[] = 'Run Choice Validation before finalization.';
        }

        if (! $run || $run->status !== 'completed') {
            $reasons[] = 'The latest Choice Validation run must be completed.';
        } else {
            if ((int) $run->validation_version !== (int) $state->current_validation_version) {
                $reasons[] = 'The latest completed Choice Validation run is not the current validation version.';
            }

            if ((int) $run->source_version !== (int) $state->approved_source_version) {
                $reasons[] = 'Choice source changed after the current Choice Validation run.';
            }

            if (
                $currentCircularVersion !== null
                && (int) $run->circular_version !== $currentCircularVersion
            ) {
                $reasons[] = 'Finalized Circular version changed after the current Choice Validation run.';
            }
        }

        if ($state->is_stale) {
            $reasons[] = $state->stale_reason
                ?: 'Choice Validation is stale and must be regenerated/revalidated.';
        }

        if ($pendingManualCorrections > 0) {
            $reasons[] = "{$pendingManualCorrections} manual Choice correction(s) are still waiting for revalidation.";
        }

        if ($sourceCount > 0 && $resultCount !== $sourceCount) {
            $reasons[] = "Choice Validation result count ({$resultCount}) does not match approved source count ({$sourceCount}).";
        }

        if (
            $run
            && (int) $run->processed_candidates !== (int) $run->total_candidates
        ) {
            $reasons[] = 'The latest Choice Validation run did not process every expected candidate.';
        }

        if (
            (int) ($state->finalized_validation_version ?? 0) > 0
            && (int) $state->finalized_validation_version === (int) $state->current_validation_version
            && $state->latest_finalization_run_id
            && ! $state->is_stale
        ) {
            $finalized = ChoiceValidationFinalizationRun::query()
                ->find($state->latest_finalization_run_id);

            if ($finalized) {
                $currentHash = $this->hasher->hash((int) $state->current_validation_version);
                if (hash_equals((string) $finalized->dataset_hash, $currentHash)) {
                    $reasons[] = 'The current Choice Validation dataset is already finalized.';
                } else {
                    $reasons[] = 'Finalized Choice Validation dataset hash mismatch detected. Reprocess before finalizing again.';
                }
            }
        }

        $reasons = array_values(array_unique(array_filter($reasons)));

        return [
            'ready' => $reasons === [],
            'reasons' => $reasons,
            'state' => $state,
            'run' => $run,
            'source_batch' => $sourceBatch,
            'result_count' => $resultCount,
            'source_count' => $sourceCount,
            'pending_manual_corrections' => $pendingManualCorrections,
            'current_circular_version' => $currentCircularVersion,
        ];
    }

    public function finalize(User $actor, string $note): ChoiceValidationFinalizationRun
    {
        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages([
                'finalization_note' => 'A finalization note is required.',
            ]);
        }

        $readiness = $this->readiness();

        if (! $readiness['ready']) {
            throw ValidationException::withMessages([
                'finalization' => $readiness['reasons'],
            ]);
        }

        /** @var ChoiceValidationProcessingState $state */
        $state = $readiness['state'];
        /** @var ChoiceValidationRun $run */
        $run = $readiness['run'];

        $hash = $this->hasher->hash((int) $run->validation_version);

        $finalization = DB::connection('exam')->transaction(function () use (
            $state,
            $run,
            $actor,
            $note,
            $hash
        ): ChoiceValidationFinalizationRun {
            $locked = ChoiceValidationProcessingState::query()
                ->lockForUpdate()
                ->findOrFail(1);

            if (
                (int) $locked->current_validation_version !== (int) $run->validation_version
                || (bool) $locked->is_stale
            ) {
                throw ValidationException::withMessages([
                    'finalization' => 'Choice Validation state changed while finalization was being prepared. Review and try again.',
                ]);
            }

            $finalization = ChoiceValidationFinalizationRun::query()->create([
                'source_version' => (int) $run->source_version,
                'validation_version' => (int) $run->validation_version,
                'circular_version' => (int) $run->circular_version,
                'validation_run_id' => (int) $run->id,
                'status' => 'finalized',
                'dataset_hash' => $hash,
                'total_candidates' => (int) $run->total_candidates,
                'valid_candidates' => (int) $run->valid_candidates,
                'not_applicable_candidates' => (int) $run->not_applicable_candidates,
                'zero_valid_choice_candidates' => (int) $run->zero_valid_choice_candidates,
                'kept_choices' => (int) $run->kept_choices,
                'removed_choices' => (int) $run->removed_choices,
                'expanded_choices' => (int) $run->expanded_choices,
                'finalized_by' => (int) $actor->id,
                'finalized_by_name' => $actor->name ?? null,
                'finalization_note' => $note,
                'finalized_at' => now(),
                'created_at' => now(),
            ]);

            $summary = (array) $locked->summary;
            $summary['finalization'] = [
                'finalization_run_id' => (int) $finalization->id,
                'validation_run_id' => (int) $run->id,
                'source_version' => (int) $run->source_version,
                'validation_version' => (int) $run->validation_version,
                'circular_version' => (int) $run->circular_version,
                'dataset_hash' => $hash,
                'finalized_at' => now()->toIso8601String(),
                'finalized_by' => (int) $actor->id,
            ];

            $locked->update([
                'status' => 'finalized',
                'finalized_validation_version' => (int) $run->validation_version,
                'latest_finalization_run_id' => (int) $finalization->id,
                'finalized_at' => now(),
                'is_stale' => false,
                'stale_reason' => null,
                'summary' => $summary,
            ]);

            ChoiceValidationProcessingAudit::query()->create([
                'action' => 'CHOICE_VALIDATION_FINALIZED',
                'actor_id' => (int) $actor->id,
                'actor_name' => $actor->name ?? null,
                'reason' => $note,
                'summary' => [
                    'finalization_run_id' => (int) $finalization->id,
                    'validation_run_id' => (int) $run->id,
                    'source_version' => (int) $run->source_version,
                    'validation_version' => (int) $run->validation_version,
                    'circular_version' => (int) $run->circular_version,
                    'dataset_hash' => $hash,
                    'total_candidates' => (int) $run->total_candidates,
                    'valid_candidates' => (int) $run->valid_candidates,
                    'not_applicable_candidates' => (int) $run->not_applicable_candidates,
                    'zero_valid_choice_candidates' => (int) $run->zero_valid_choice_candidates,
                ],
                'created_at' => now(),
            ]);

            return $finalization;
        }, 3);

        Log::channel('stack')->info('CHOICE_VALIDATION_FINALIZED', [
            'finalization_run_id' => $finalization->id,
            'validation_version' => $finalization->validation_version,
            'dataset_hash' => $finalization->dataset_hash,
            'actor_id' => $actor->id,
        ]);

        return $finalization;
    }
}
