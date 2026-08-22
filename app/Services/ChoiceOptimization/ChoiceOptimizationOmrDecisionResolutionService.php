<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationOmrStaging;
use App\Models\ChoiceOptimizationProcessingAudit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ChoiceOptimizationOmrDecisionResolutionService
{
    public function resolve(ChoiceOptimizationOmrStaging $row, string $resolution, string $reason, ?int $actorId): void
    {
        $allowed = ['consider_no_as_yes_keep_options', 'keep_no_discard_options'];
        if (! in_array($resolution, $allowed, true)) {
            throw new InvalidArgumentException('Invalid OMR decision resolution.');
        }

        if (strtoupper((string) $row->change_choice) !== 'NO' || (int) $row->raw_choice_count < 1) {
            throw new InvalidArgumentException('Decision resolution is only available for change_choice=NO rows containing OMR options.');
        }

        DB::connection('exam')->transaction(function () use ($row, $resolution, $reason, $actorId): void {
            $effective = $resolution === 'consider_no_as_yes_keep_options' ? 'YES' : 'NO';

            $row->update([
                'effective_change_choice' => $effective,
                'decision_resolution' => $resolution,
                'decision_resolution_reason' => trim($reason),
                'decision_resolved_by' => $actorId,
                'decision_resolved_at' => now(),
                'validation_status' => 'pending',
                'choice_validation_status' => 'not_started',
                'validated_omr_choice_codes' => null,
                'choice_validation_details' => null,
            ]);

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => 'omr_decision_resolved',
                'actor_id' => $actorId,
                'context' => [
                    'omr_staging_id' => $row->id,
                    'batch_id' => $row->batch_id,
                    'raw_reg' => $row->raw_reg,
                    'effective_reg' => $row->effective_reg,
                    'raw_change_choice' => $row->change_choice,
                    'effective_change_choice' => $effective,
                    'resolution' => $resolution,
                    'reason' => trim($reason),
                ],
                'created_at' => now(),
            ]);
        });
    }
}
