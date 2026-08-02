<?php

namespace App\Services\Preliminary;

use App\Enums\PreliminaryProcessingStatus;
use App\Models\PreliminaryProcessingState;
use App\Models\PreliminaryResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PreliminaryResultEditService
{
    public function __construct(
        private readonly PreliminaryRowInterpreter $interpreter,
        private readonly PreliminaryAuditService $audit,
    ) {}

    public function update(
        PreliminaryResult $result,
        mixed $mark,
        ?string $sourceNote,
        string $status,
        string $reason,
        User $actor,
    ): PreliminaryResult
    {
        $interpreted = $this->interpreter->interpret($mark, $sourceNote);
        if ($interpreted['errors'] !== []) {
            throw ValidationException::withMessages(['mark' => $interpreted['errors']]);
        }

        $rawStatus = trim((string) ($sourceNote ?? ''));
        $rawStatus = $rawStatus === '' ? null : $rawStatus;
        $validationStatus = $interpreted['warnings'] === [] ? 'valid' : 'warning';

        if ($status === 'active' && $interpreted['mark'] === null) {
            throw ValidationException::withMessages([
                'status' => ['An active Preliminary candidate must have a valid mark. Use Cancelled, Withheld or Expelled when the candidate should stay outside result processing.'],
            ]);
        }

        $before = $this->snapshot($result);
        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );
        $stateBefore = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;

        DB::connection('exam')->transaction(function () use (
            $result, $interpreted, $rawStatus, $validationStatus, $status, $reason, $actor, $state
        ): void {
            $result->update([
                'mark' => $interpreted['mark'],
                'raw_candidate_status' => $rawStatus,
                'candidate_status' => $status,
                'result_status' => $status === 'cancelled' ? 'cancelled' : null,
                'applied_cutoff_mark' => null,
                'validation_status' => $validationStatus,
                'finalized_at' => null,
                'last_edited_by' => $actor->id,
                'last_edited_at' => now(),
                'last_edit_reason' => $reason,
            ]);

            // Any manual mark/status correction invalidates every derived PASS/FAIL fact.
            // Keep source/cancellation facts, but never expose a stale finalized result.
            DB::connection('exam')->table('preliminary_results')->update([
                'result_status' => DB::raw("CASE WHEN candidate_status = 'cancelled' THEN 'cancelled' ELSE NULL END"),
                'applied_cutoff_mark' => null,
                'finalized_at' => null,
                'updated_at' => now(),
            ]);

            $existingSummary = is_array($state->summary) ? $state->summary : [];
            unset($existingSummary['finalization']);

            $state->update([
                'status' => PreliminaryProcessingStatus::Reopened->value,
                'latest_reconciliation_report_id' => null,
                'reconciliation_generated_by' => null,
                'reconciliation_generated_at' => null,
                'latest_distribution_report_id' => null,
                'distribution_generated_by' => null,
                'distribution_generated_at' => null,
                'cutoff_requires_review' => $state->cutoff_mark !== null,
                'latest_finalization_run_id' => null,
                'result_finalized_by' => null,
                'result_finalized_at' => null,
                'summary' => $existingSummary,
            ]);
        });

        $result->refresh();
        $after = $this->snapshot($result);

        $this->audit->record(
            'PRELIMINARY_RESULT_MANUAL_EDITED',
            $actor,
            $stateBefore,
            PreliminaryProcessingStatus::Reopened->value,
            $reason,
            [
                'reg' => $result->reg,
                'user_id' => $result->user_id,
                'warnings' => $interpreted['warnings'],
                'operational_status' => $status,
                'processing_eligible' => $status === 'active',
                'downstream_snapshots_invalidated' => true,
                'cutoff_requires_review' => $state->cutoff_mark !== null,
            ],
            $before,
            $after,
            batchId: $result->source_batch_id,
            registrationId: $result->registration_id,
            preliminaryResultId: $result->id,
        );

        return $result;
    }

    /** @return array<string,mixed> */
    private function snapshot(PreliminaryResult $result): array
    {
        $candidateStatus = $result->candidate_status instanceof \BackedEnum ? $result->candidate_status->value : $result->candidate_status;
        $resultStatus = $result->result_status instanceof \BackedEnum ? $result->result_status->value : $result->result_status;
        $validationStatus = $result->validation_status instanceof \BackedEnum ? $result->validation_status->value : $result->validation_status;

        return [
            'id' => $result->id,
            'registration_id' => $result->registration_id,
            'reg' => $result->reg,
            'user_id' => $result->user_id,
            'mark' => $result->mark,
            'raw_candidate_status' => $result->raw_candidate_status,
            'candidate_status' => $candidateStatus,
            'result_status' => $resultStatus,
            'validation_status' => $validationStatus,
            'applied_cutoff_mark' => $result->applied_cutoff_mark,
            'finalized_at' => optional($result->finalized_at)->toDateTimeString(),
            'last_edited_by' => $result->last_edited_by,
            'last_edited_at' => optional($result->last_edited_at)->toDateTimeString(),
            'last_edit_reason' => $result->last_edit_reason,
        ];
    }
}
