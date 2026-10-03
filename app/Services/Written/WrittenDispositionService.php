<?php

namespace App\Services\Written;

use App\Enums\WrittenProcessingStatus;
use App\Models\User;
use App\Models\WrittenProcessingState;
use App\Models\WrittenResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Applies an audited Written-phase candidate disposition without changing source marks. */
final class WrittenDispositionService
{
    public function __construct(private readonly WrittenAuditService $audit) {}

    public function update(WrittenResult $result, string $status, string $reason, User $actor): WrittenResult
    {
        if (! in_array($status, ['active', 'cancelled', 'withheld'], true)) {
            throw new RuntimeException('Unsupported Written disposition.');
        }

        $current = $result->status instanceof \BackedEnum ? $result->status->value : (string) $result->status;
        if ($current === $status) {
            return $result;
        }

        $registrationStatus = (string) DB::connection('exam')->table('registrations')
            ->where('id', $result->registration_id)->value('status');
        if ($status === 'active' && $registrationStatus !== 'active') {
            throw new RuntimeException('This candidate is not ACTIVE in Registration and cannot be restored to the Written allocation/publication population.');
        }

        $state = WrittenProcessingState::query()->firstOrCreate(
            ['id' => 1], ['status' => WrittenProcessingStatus::NotStarted->value]
        );
        $stateBefore = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
        $timestamp = now();

        DB::connection('exam')->transaction(function () use ($result, $status, $reason, $actor, $state, $timestamp): void {
            $result->update([
                'status' => $status,
                'general_result_status' => null,
                'technical_result_status' => null,
                'written_qualified_track' => null,
                'general_actual_total' => null,
                'general_counted_total' => null,
                'technical_actual_total' => null,
                'technical_counted_total' => null,
                'general_fail_reasons' => null,
                'technical_fail_reasons' => null,
                'finalized_at' => null,
                'last_edited_by' => $actor->id,
                'last_edited_at' => $timestamp,
                'last_edit_reason' => $reason,
            ]);

            DB::connection('exam')->table('written_candidate_marks')->update([
                'counted_mark' => DB::raw('actual_mark'),
                'paper_crashed' => false,
                'updated_at' => $timestamp,
            ]);
            DB::connection('exam')->table('written_results')->update([
                'general_result_status' => null,
                'technical_result_status' => null,
                'written_qualified_track' => null,
                'general_actual_total' => null,
                'general_counted_total' => null,
                'technical_actual_total' => null,
                'technical_counted_total' => null,
                'general_fail_reasons' => null,
                'technical_fail_reasons' => null,
                'finalized_at' => null,
                'updated_at' => $timestamp,
            ]);

            $state->update([
                'status' => WrittenProcessingStatus::Reopened->value,
                'latest_reconciliation_report_id' => null,
                'reconciliation_generated_by' => null,
                'reconciliation_generated_at' => null,
                'latest_processing_run_id' => null,
                'paper_crash_processed_by' => null,
                'paper_crash_processed_at' => null,
                'result_finalized_by' => null,
                'result_finalized_at' => null,
                'summary' => null,
                'is_stale' => true,
                'stale_reason' => "Written disposition changed for REG {$result->reg}. Regenerate reconciliation and reprocess Written rules.",
            ]);
        });

        $this->audit->record(
            'WRITTEN_CANDIDATE_DISPOSITION_CHANGED', $actor, $stateBefore,
            WrittenProcessingStatus::Reopened->value, $reason,
            changedFields: ['status' => ['before' => $current, 'after' => $status]],
            summary: ['reg' => $result->reg, 'user_id' => $result->user_id, 'registration_status' => $registrationStatus],
            batchId: $result->source_batch_id, registrationId: $result->registration_id, writtenResultId: $result->id,
        );

        return $result->refresh();
    }
}
