<?php

namespace App\Services\Preliminary;

use App\Enums\PreliminaryProcessingStatus;
use App\Models\PreliminaryProcessingState;
use App\Models\PreliminaryResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Applies an optional, audited Preliminary-phase disposition without changing source marks. */
final class PreliminaryDispositionService
{
    public function __construct(private readonly PreliminaryAuditService $audit) {}

    public function update(PreliminaryResult $result, string $status, string $reason, User $actor): PreliminaryResult
    {
        if (! in_array($status, ['active', 'cancelled', 'withheld'], true)) {
            throw new RuntimeException('Unsupported Preliminary disposition.');
        }

        $current = $result->candidate_status instanceof \BackedEnum ? $result->candidate_status->value : (string) $result->candidate_status;
        if ($current === $status) {
            return $result;
        }

        $registrationStatus = (string) DB::connection('exam')->table('registrations')
            ->where('id', $result->registration_id)->value('status');
        if ($status === 'active' && $registrationStatus !== 'active') {
            throw new RuntimeException('This candidate is not ACTIVE in Registration and cannot be restored to the Preliminary result/publication population.');
        }

        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1], ['status' => PreliminaryProcessingStatus::NotStarted->value]
        );
        $beforeState = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
        $timestamp = now();

        DB::connection('exam')->transaction(function () use ($result, $status, $reason, $actor, $state, $timestamp): void {
            $result->update([
                'candidate_status' => $status,
                'result_status' => $status === 'cancelled' ? 'cancelled' : null,
                'applied_cutoff_mark' => null,
                'finalized_at' => null,
                'last_edited_by' => $actor->id,
                'last_edited_at' => $timestamp,
                'last_edit_reason' => $reason,
            ]);

            // A disposition change invalidates all derived PASS/FAIL/publication facts.
            DB::connection('exam')->table('preliminary_results')->update([
                'result_status' => DB::raw("CASE WHEN candidate_status = 'cancelled' THEN 'cancelled' ELSE NULL END"),
                'applied_cutoff_mark' => null,
                'finalized_at' => null,
                'updated_at' => $timestamp,
            ]);

            $summary = is_array($state->summary) ? $state->summary : [];
            unset($summary['finalization']);
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
                'summary' => $summary,
            ]);
        });

        $this->audit->record(
            'PRELIMINARY_CANDIDATE_DISPOSITION_CHANGED', $actor, $beforeState,
            PreliminaryProcessingStatus::Reopened->value, $reason,
            ['reg' => $result->reg, 'user_id' => $result->user_id, 'status_before' => $current, 'status_after' => $status,
                'registration_status' => $registrationStatus, 'processing_eligible' => $status === 'active'],
            ['candidate_status' => $current], ['candidate_status' => $status],
            batchId: $result->source_batch_id, registrationId: $result->registration_id, preliminaryResultId: $result->id,
        );

        return $result->refresh();
    }
}
