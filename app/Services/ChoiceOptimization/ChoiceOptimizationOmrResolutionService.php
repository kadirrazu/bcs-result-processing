<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationOmrBatch;
use App\Models\ChoiceOptimizationOmrStaging;
use App\Models\ChoiceOptimizationProcessingAudit;
use Illuminate\Validation\ValidationException;

final class ChoiceOptimizationOmrResolutionService
{
    public function correctRegistration(ChoiceOptimizationOmrStaging $row, string $effectiveReg, string $reason, ?int $actorId): void
    {
        $effectiveReg = trim($effectiveReg);
        $reason = trim($reason);
        if ($effectiveReg === '' || $reason === '') {
            throw ValidationException::withMessages(['resolution' => 'Corrected registration and resolution reason are required.']);
        }

        $old = (string) ($row->effective_reg ?? '');
        $row->update([
            'effective_reg' => $effectiveReg,
            'registration_id' => null,
            'written_qualified_track' => null,
            'validation_status' => 'pending',
            'validation_errors' => null,
            'validation_warnings' => null,
            'resolution_status' => 'resolved',
            'resolution_reason' => $reason,
            'resolved_by' => $actorId,
            'resolved_at' => now(),
        ]);

        ChoiceOptimizationProcessingAudit::query()->create([
            'event' => 'omr_registration_corrected',
            'actor_id' => $actorId,
            'context' => [
                'batch_id' => (int) $row->batch_id,
                'staging_row_id' => (int) $row->id,
                'source_row' => (int) $row->source_row,
                'raw_reg' => $row->raw_reg,
                'old_effective_reg' => $old,
                'new_effective_reg' => $effectiveReg,
                'reason' => $reason,
            ],
        ]);

        ChoiceOptimizationOmrBatch::query()->whereKey($row->batch_id)->update([
            'status' => 'staged', 'validated_at' => null, 'finished_at' => null,
        ]);
    }
}
