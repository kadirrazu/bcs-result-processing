<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationEffectiveChoice;
use App\Models\ChoiceOptimizationOmrBatch;
use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationProcessingState;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChoiceOptimizationOmrApprovalService
{
    public function __construct(private readonly ChoiceValidationFinalizedDatasetService $choiceValidation) {}

    public function approve(int $batchId, int $actorId): ChoiceOptimizationOmrBatch
    {
        $batch = ChoiceOptimizationOmrBatch::query()->findOrFail($batchId);
        if (! in_array((string) $batch->status, ['approval_queued', 'validated'], true)) {
            throw new RuntimeException('Only a fully validated OMR batch can be approved.');
        }

        $blocking = $batch->stagingRows()
            ->whereIn('validation_status', ['invalid', 'conflict', 'decision_review', 'pending'])
            ->count();
        if ($blocking > 0) {
            throw new RuntimeException('Resolve every invalid/conflict/decision-review OMR row and re-run validation before approval.');
        }

        $summary = $this->choiceValidation->verifiedSummary();
        $results = $this->choiceValidation->choiceReadyResults()->keyBy('registration_id');
        $omrRows = $batch->stagingRows()->where('validation_status', 'valid')->get()->keyBy('registration_id');

        $batch->update([
            'status' => 'approving',
            'processed_rows' => 0,
            'approved_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'finished_at' => null,
        ]);

        $now = now();
        $rows = [];
        foreach ($results as $registrationId => $result) {
            $omr = $omrRows->get((int) $registrationId);
            $validated = array_values((array) $result->validated_choice_codes);
            $effective = $validated;
            $source = 'validated_choice';
            $reasonCode = 'UNCHANGED_FROM_VALIDATED_CHOICE';
            $reasonText = 'No effective Viva OMR override was applied.';
            $override = null;

            if ($omr && strtoupper((string) $omr->effective_change_choice) === 'YES') {
                $override = array_values(array_filter(
                    (array) $omr->validated_omr_choice_codes,
                    static fn ($code): bool => trim((string) $code) !== '',
                ));

                if ((string) $omr->choice_validation_status !== 'valid' || $override === []) {
                    throw new RuntimeException('An effective OMR YES override must have a clean, non-empty validated OMR choice sequence before approval.');
                }

                $effective = $override;
                $source = 'viva_omr_override';
                $reasonCode = 'OVERRIDDEN_BY_VIVA_OMR';
                $reasonText = $omr->decision_resolution === 'consider_no_as_yes_keep_options'
                    ? 'Operator interpreted OMR NO-with-options as YES and retained the validated OMR options.'
                    : 'Candidate explicitly selected YES and supplied a valid OMR replacement choice list.';
            } elseif ($omr && strtoupper((string) $omr->effective_change_choice) === 'NO') {
                $source = 'validated_choice';
                $reasonCode = $omr->decision_resolution === 'keep_no_discard_options'
                    ? 'OMR_NO_OPTIONS_DISCARDED_BY_OPERATOR'
                    : 'OMR_NO_CHANGE';
                $reasonText = $omr->decision_resolution === 'keep_no_discard_options'
                    ? 'Operator retained NO and discarded the OMR options from the effective pipeline while preserving the raw form.'
                    : 'Candidate selected NO; finalized validated choice remains effective.';
            }

            $rows[] = [
                'registration_id' => (int) $registrationId,
                'reg' => (string) $result->reg,
                'choice_validation_result_id' => (int) $result->id,
                'omr_staging_id' => $omr?->id,
                'choice_source' => $source,
                'validated_choice_codes' => json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'omr_override_choice_codes' => $override !== null ? json_encode($override, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
                'effective_choice_codes' => json_encode($effective, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'change_reason_code' => $reasonCode,
                'change_reason_text' => $reasonText,
                'approved_by' => $actorId,
                'approved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection('exam')->transaction(function () use ($rows, $batch, $actorId, $summary, $now): void {
            ChoiceOptimizationEffectiveChoice::query()->delete();
            $chunk = max(100, (int) config('choice-optimization.import_chunk_size', 1000));
            $done = 0;
            foreach (array_chunk($rows, $chunk) as $part) {
                DB::connection('exam')->table('choice_optimization_effective_choices')->insert($part);
                $done += count($part);
                $batch->update([
                    'processed_rows' => $done,
                    'approved_rows' => $done,
                    'progress_percent' => count($rows) > 0 ? round($done * 100 / count($rows), 4) : 100,
                ]);
            }

            $batch->update([
                'status' => 'approved',
                'processed_rows' => count($rows),
                'approved_rows' => count($rows),
                'progress_percent' => 100,
                'approved_at' => $now,
                'finished_at' => $now,
            ]);

            $state = ChoiceOptimizationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
            $state->update([
                'status' => 'omr_effective_choices_approved',
                'is_stale' => false,
                'stale_reason' => null,
                'source_snapshot' => [
                    'choice_validation_version' => $summary['validation_version'] ?? null,
                    'choice_validation_hash' => $summary['dataset_hash'] ?? null,
                    'omr_batch_id' => $batch->id,
                ],
                'summary' => array_merge((array) $state->summary, [
                    'omr' => [
                        'batch_id' => $batch->id,
                        'total_rows' => $batch->total_rows,
                        'effective_choice_rows' => count($rows),
                        'approved_at' => $now->toIso8601String(),
                    ],
                ]),
            ]);

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => 'omr_effective_choices_approved',
                'actor_id' => $actorId,
                'from_status' => 'validated',
                'to_status' => 'omr_effective_choices_approved',
                'context' => [
                    'omr_batch_id' => $batch->id,
                    'effective_choice_rows' => count($rows),
                    'choice_validation_version' => $summary['validation_version'] ?? null,
                    'choice_validation_hash' => $summary['dataset_hash'] ?? null,
                ],
                'created_at' => $now,
            ]);
        });

        return $batch->refresh();
    }
}
