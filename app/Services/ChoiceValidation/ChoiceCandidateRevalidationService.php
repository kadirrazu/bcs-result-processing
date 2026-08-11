<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceSource;
use App\Models\ChoiceValidationItem;
use App\Models\ChoiceValidationManualCorrection;
use App\Models\ChoiceValidationProcessingAudit;
use App\Models\ChoiceValidationProcessingState;
use App\Models\ChoiceValidationResult;
use App\Models\ChoiceValidationRun;
use App\Models\Registration;
use App\Models\User;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChoiceCandidateRevalidationService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceCandidateTrackResolver $tracks,
        private readonly ChoiceValidationEngine $engine,
        private readonly ChoiceEffectiveSourceResolver $effectiveSource,
    ) {}

    public function revalidate(ChoiceValidationResult $result, User $actor, ?ChoiceValidationManualCorrection $correction = null): ChoiceValidationResult
    {
        $state = ChoiceValidationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        if ((int) $state->current_validation_version !== (int) $result->validation_version) {
            throw new RuntimeException('Only the current Choice Validation version can be manually revalidated.');
        }
        if ((int) $this->circular->finalizedVersion() !== (int) $result->circular_version) {
            throw new RuntimeException('The finalized Circular version changed. Run full Choice Validation again.');
        }

        /** @var ChoiceSource $source */
        $source = ChoiceSource::query()->findOrFail($result->choice_source_id);
        /** @var Registration $registration */
        $registration = Registration::query()->findOrFail($source->registration_id);
        $track = $this->tracks->resolve($registration->id);
        $out = $this->engine->validate(
            $registration,
            $track,
            $this->effectiveSource->items($source),
            $this->circular->entries(),
        );

        DB::connection('exam')->transaction(function () use ($result, $source, $registration, $track, $out, $correction): void {
            ChoiceValidationItem::query()->where('choice_validation_result_id', $result->id)->delete();

            $result->update([
                'written_qualified_track' => $track['written_track'],
                'effective_track' => $track['track'],
                'status' => $out['status'],
                'result_reason_code' => $out['reason'],
                'validated_choice_codes' => $out['validated'],
                'original_choice_count' => count($this->effectiveSource->items($source)),
                'validated_choice_count' => count($out['validated']),
                'removed_choice_count' => $out['removed'],
                'expanded_choice_count' => $out['expanded'],
                'eligibility_snapshot' => [
                    'bachelor_subject_code' => $registration->bachelor_subject_code,
                    'post_related_subject_code' => $registration->post_related_subject_code,
                    'track' => $track,
                    'manual_correction_applied' => true,
                ],
                'processed_at' => now(),
            ]);

            foreach ($out['details'] as $detail) {
                $detail['choice_validation_result_id'] = $result->id;
                ChoiceValidationItem::query()->create($detail);
            }

            if ($correction) {
                $correction->update(['revalidated_at' => now()]);
            }

            $this->refreshRunAndState($result);
        }, 3);

        ChoiceValidationProcessingAudit::query()->create([
            'action' => 'CHOICE_CANDIDATE_REVALIDATED',
            'actor_id' => $actor->id,
            'actor_name' => $actor->name ?? null,
            'reason' => 'Candidate revalidated after audited Choice correction.',
            'summary' => [
                'reg' => $result->reg,
                'result_id' => $result->id,
                'validation_version' => $result->validation_version,
                'status' => $out['status'],
                'validated_choice_codes' => $out['validated'],
            ],
            'created_at' => now(),
        ]);

        return $result->refresh();
    }

    private function refreshRunAndState(ChoiceValidationResult $result): void
    {
        $query = ChoiceValidationResult::query()->where('validation_version', $result->validation_version);
        $run = ChoiceValidationRun::query()->where('validation_version', $result->validation_version)->latest('id')->first();
        if ($run) {
            $run->update([
                'valid_candidates' => (clone $query)->where('status', 'valid')->count(),
                'not_applicable_candidates' => (clone $query)->where('status', 'like', 'not_applicable%')->count(),
                'zero_valid_choice_candidates' => (clone $query)->where('status', 'no_valid_choices')->count(),
                'kept_choices' => (int) (clone $query)->sum('validated_choice_count'),
                'removed_choices' => (int) (clone $query)->sum('removed_choice_count'),
                'expanded_choices' => (int) (clone $query)->sum('expanded_choice_count'),
            ]);
        }

        $pending = ChoiceValidationManualCorrection::query()
            ->where('source_version', $result->source_version)
            ->where('validation_version', $result->validation_version)
            ->whereNull('revalidated_at')
            ->exists();

        $state = ChoiceValidationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $state->update([
            'status' => $pending ? 'validation_needs_review' : 'validation_completed',
            'is_stale' => $pending,
            'stale_reason' => $pending ? 'One or more manual Choice corrections are waiting for revalidation.' : null,
        ]);
    }
}
