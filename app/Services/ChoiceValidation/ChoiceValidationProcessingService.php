<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceSource;
use App\Models\ChoiceValidationProcessingState;
use App\Models\ChoiceValidationResult;
use App\Models\ChoiceValidationRun;
use App\Models\Registration;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChoiceValidationProcessingService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceCandidateTrackResolver $tracks,
        private readonly ChoiceValidationEngine $engine,
        private readonly ChoiceEffectiveSourceResolver $effectiveSource,
    ) {}

    public function run(int $runId): void
    {
        $run = ChoiceValidationRun::query()->findOrFail($runId);
        $this->tracks->assertVivaReady();
        $circularVersion = $this->circular->finalizedVersion();

        if ($circularVersion !== $run->circular_version) {
            throw new RuntimeException('Finalized Circular version changed before Choice Validation started.');
        }

        $entries = $this->circular->entries();
        $this->engine->prepare($entries);

        // A failed/retried run of the same validation version must not duplicate
        // results/items. FK cascade removes detail rows.
        ChoiceValidationResult::query()
            ->where('validation_version', $run->validation_version)
            ->delete();

        $run->update([
            'status' => 'running',
            'processed_candidates' => 0,
            'valid_candidates' => 0,
            'not_applicable_candidates' => 0,
            'zero_valid_choice_candidates' => 0,
            'kept_choices' => 0,
            'removed_choices' => 0,
            'expanded_choices' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $total = (int) $run->total_candidates;
        $processed = $valid = $notApplicable = $zero = $kept = $removed = $expanded = 0;
        $chunkSize = max(100, (int) config('choice-validation.processing_chunk_size', 500));
        $detailInsertChunk = max(250, (int) config('choice-validation.detail_insert_chunk_size', 1000));

        ChoiceSource::query()
            ->where('source_version', $run->source_version)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($sources) use (
                $run, $entries, $total, $detailInsertChunk,
                &$processed, &$valid, &$notApplicable, &$zero, &$kept, &$removed, &$expanded
            ): void {
                $registrationIds = $sources->pluck('registration_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
                $registrations = Registration::query()->whereIn('id', $registrationIds)->get()->keyBy('id');
                $trackMap = $this->tracks->resolveMany($registrationIds);
                $this->effectiveSource->preload($sources);
                $now = now();
                $resultRows = [];
                $detailByRegistration = [];

                foreach ($sources as $source) {
                    $registration = $registrations->get((int) $source->registration_id);
                    if (! $registration) {
                        throw new RuntimeException('Approved Choice source references a missing Registration.');
                    }

                    $track = $trackMap[(int) $registration->id]
                        ?? ['eligible' => false, 'track' => null, 'written_track' => null];

                    $effectiveItems = $this->effectiveSource->items($source);
                    $output = $this->engine->validate(
                        $registration,
                        $track,
                        $effectiveItems,
                        $entries,
                    );

                    $resultRows[] = [
                        'choice_source_id' => $source->id,
                        'registration_id' => $registration->id,
                        'reg' => $source->reg,
                        'user_id' => $source->user_id,
                        'source_version' => $run->source_version,
                        'validation_version' => $run->validation_version,
                        'circular_version' => $run->circular_version,
                        'written_qualified_track' => $track['written_track'],
                        'effective_track' => $track['track'],
                        'status' => $output['status'],
                        'result_reason_code' => $output['reason'],
                        'validated_choice_codes' => $this->json($output['validated']),
                        'original_choice_count' => count($effectiveItems),
                        'validated_choice_count' => count($output['validated']),
                        'removed_choice_count' => $output['removed'],
                        'expanded_choice_count' => $output['expanded'],
                        'eligibility_snapshot' => $this->json([
                            'bachelor_subject_code' => $registration->bachelor_subject_code,
                            'post_related_subject_code' => $registration->post_related_subject_code,
                            'track' => $track,
                        ]),
                        'processing_run_id' => $run->id,
                        'processed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $detailByRegistration[(int) $registration->id] = $output['details'];
                    $processed++;
                    $kept += count($output['validated']);
                    $removed += $output['removed'];
                    $expanded += $output['expanded'];

                    if ($output['status'] === 'valid') {
                        $valid++;
                    } elseif (str_starts_with((string) $output['status'], 'not_applicable')) {
                        $notApplicable++;
                    } else {
                        $zero++;
                    }
                }

                DB::connection('exam')->transaction(function () use (
                    $resultRows, $detailByRegistration, $run, $registrationIds, $detailInsertChunk, $now
                ): void {
                    DB::connection('exam')->table('choice_validation_results')->insert($resultRows);

                    $resultIds = ChoiceValidationResult::query()
                        ->where('validation_version', $run->validation_version)
                        ->whereIn('registration_id', $registrationIds)
                        ->pluck('id', 'registration_id');

                    $itemRows = [];
                    foreach ($detailByRegistration as $registrationId => $details) {
                        $resultId = $resultIds->get($registrationId);
                        if (! $resultId) {
                            throw new RuntimeException('Choice Validation result id could not be resolved after bulk insert.');
                        }

                        foreach ($details as $detail) {
                            $itemRows[] = [
                                'choice_validation_result_id' => $resultId,
                                'source_position' => $detail['source_position'],
                                'source_column' => $detail['source_column'],
                                'source_code' => $detail['source_code'],
                                'resolved_type' => $detail['resolved_type'],
                                'resolved_cadre_id' => $detail['resolved_cadre_id'],
                                'resolved_sub_cadre_id' => $detail['resolved_sub_cadre_id'],
                                'result' => $detail['result'],
                                'reason_code' => $detail['reason_code'],
                                'reason_message' => $detail['reason_message'],
                                'output_position' => $detail['output_position'],
                                'output_code' => $detail['output_code'],
                                'expanded_from_code' => $detail['expanded_from_code'],
                                'circular_entry_id' => $detail['circular_entry_id'],
                                'eligibility_snapshot' => $this->json($detail['eligibility_snapshot']),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    foreach (array_chunk($itemRows, $detailInsertChunk) as $itemChunk) {
                        DB::connection('exam')->table('choice_validation_items')->insert($itemChunk);
                    }
                });

                // Progress is intentionally persisted once per processing chunk,
                // not once per candidate.
                $run->update([
                    'processed_candidates' => $processed,
                    'valid_candidates' => $valid,
                    'not_applicable_candidates' => $notApplicable,
                    'zero_valid_choice_candidates' => $zero,
                    'kept_choices' => $kept,
                    'removed_choices' => $removed,
                    'expanded_choices' => $expanded,
                    'progress_percent' => $total > 0 ? round($processed * 100 / $total, 4) : 100,
                ]);
            });

        $run->update([
            'status' => 'completed',
            'processed_candidates' => $processed,
            'valid_candidates' => $valid,
            'not_applicable_candidates' => $notApplicable,
            'zero_valid_choice_candidates' => $zero,
            'kept_choices' => $kept,
            'removed_choices' => $removed,
            'expanded_choices' => $expanded,
            'progress_percent' => 100,
            'finished_at' => now(),
        ]);

        $state = ChoiceValidationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $state->update([
            'status' => 'validation_completed',
            'current_validation_version' => $run->validation_version,
            'latest_validation_run_id' => $run->id,
            'validation_completed_at' => now(),
            'is_stale' => false,
            'stale_reason' => null,
            'summary' => array_merge((array) $state->summary, [
                'validation' => [
                    'run_id' => $run->id,
                    'source_version' => $run->source_version,
                    'validation_version' => $run->validation_version,
                    'circular_version' => $run->circular_version,
                    'valid_candidates' => $valid,
                    'not_applicable_candidates' => $notApplicable,
                    'zero_valid_choice_candidates' => $zero,
                    'kept_choices' => $kept,
                    'removed_choices' => $removed,
                    'expanded_choices' => $expanded,
                ],
            ]),
        ]);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
