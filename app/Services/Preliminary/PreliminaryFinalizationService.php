<?php

namespace App\Services\Preliminary;

use App\Enums\PreliminaryProcessingStatus;
use App\Models\PreliminaryCutoffDecision;
use App\Models\PreliminaryFinalizationRun;
use App\Models\PreliminaryProcessingState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class PreliminaryFinalizationService
{
    public function __construct(private readonly PreliminaryAuditService $audit) {}

    public function finalize(int $runId, int $actorId): PreliminaryFinalizationRun
    {
        $run = PreliminaryFinalizationRun::query()->findOrFail($runId);
        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        if (! in_array($run->status, ['queued', 'failed'], true)) {
            throw new RuntimeException('Only a queued/failed finalization run can be processed.');
        }

        if ($state->current_cutoff_decision_id === null || $state->cutoff_mark === null) {
            throw new RuntimeException('An approved cut-off is required before finalization.');
        }

        if ((bool) $state->cutoff_requires_review) {
            throw new RuntimeException('The approved cut-off requires review before result finalization.');
        }

        $decision = PreliminaryCutoffDecision::query()->findOrFail($state->current_cutoff_decision_id);
        if ($decision->status !== 'approved') {
            throw new RuntimeException('Current cut-off decision is not approved.');
        }

        $beforeStatus = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
        $beforeSnapshot = $this->stateSnapshot($state);
        $cutoff = (float) $decision->cutoff_mark;
        $timestamp = now();

        $activeRows = DB::connection('exam')->table('preliminary_results as p')
            ->join('registrations as r', 'r.id', '=', 'p.registration_id')
            ->where('r.status', 'active')
            ->where('p.candidate_status', 'active')
            ->whereNotNull('p.mark')
            ->count();

        $cancelledRows = DB::connection('exam')->table('preliminary_results')
            ->where('candidate_status', 'cancelled')
            ->count();

        $totalRows = (int) $activeRows + (int) $cancelledRows;

        $run->update([
            'status' => 'running',
            'cutoff_decision_id' => $decision->id,
            'cutoff_mark' => $cutoff,
            'started_at' => $timestamp,
            'completed_at' => null,
            'failure_message' => null,
            'current_step' => 'Preparing result rows',
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'progress_percent' => 2,
        ]);

        $state->update(['status' => PreliminaryProcessingStatus::ResultFinalizing->value]);

        try {
            // Each phase is committed separately so progress stays visible to browser polling.
            // Official result pages remain locked until the processing state is RESULT_FINALIZED.
            $this->updateProgress($run, 4, 0, 'Resetting previous derived result state');
            DB::connection('exam')->table('preliminary_results')->update([
                'result_status' => DB::raw("CASE WHEN candidate_status = 'cancelled' THEN 'cancelled' ELSE NULL END"),
                'applied_cutoff_mark' => null,
                'finalized_at' => null,
                'updated_at' => $timestamp,
            ]);
            $this->updateProgress($run, 8, 0, 'Applying PASS result');

            $passed = DB::connection('exam')->table('preliminary_results as p')
                ->join('registrations as r', 'r.id', '=', 'p.registration_id')
                ->where('r.status', 'active')
                ->where('p.candidate_status', 'active')
                ->whereNotNull('p.mark')
                ->where('p.mark', '>=', $cutoff)
                ->update([
                    'p.result_status' => 'pass',
                    'p.applied_cutoff_mark' => $cutoff,
                    'p.finalized_at' => $timestamp,
                    'p.updated_at' => $timestamp,
                ]);

            $processed = (int) $passed;
            $this->updateRowProgress($run, $processed, $totalRows, 'PASS classification completed');
            $this->updateProgress($run, (float) $run->progress_percent, $processed, 'Applying FAIL result');

            $failed = DB::connection('exam')->table('preliminary_results as p')
                ->join('registrations as r', 'r.id', '=', 'p.registration_id')
                ->where('r.status', 'active')
                ->where('p.candidate_status', 'active')
                ->whereNotNull('p.mark')
                ->where('p.mark', '<', $cutoff)
                ->update([
                    'p.result_status' => 'fail',
                    'p.applied_cutoff_mark' => $cutoff,
                    'p.finalized_at' => $timestamp,
                    'p.updated_at' => $timestamp,
                ]);

            $processed += (int) $failed;
            $this->updateRowProgress($run, $processed, $totalRows, 'FAIL classification completed');
            $this->updateProgress($run, (float) $run->progress_percent, $processed, 'Finalizing cancelled candidates');

            $cancelled = DB::connection('exam')->table('preliminary_results')
                ->where('candidate_status', 'cancelled')
                ->update([
                    'result_status' => 'cancelled',
                    'finalized_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

            $processed += (int) $cancelled;
            $this->updateRowProgress($run, $processed, $totalRows, 'Cancelled candidates completed');

            $this->updateProgress($run, 96, min($processed, $totalRows), 'Generating final result summary');
            $summary = $this->summary();

            DB::connection('exam')->transaction(function () use ($run, $state, $summary, $actorId, $timestamp, $totalRows): void {
                $run->update([
                    'status' => 'completed',
                    'summary' => $summary,
                    'completed_at' => $timestamp,
                    'current_step' => 'Completed',
                    'processed_rows' => $totalRows,
                    'progress_percent' => 100,
                ]);

                $existing = is_array($state->summary) ? $state->summary : [];
                $state->update([
                    'status' => PreliminaryProcessingStatus::ResultFinalized->value,
                    'latest_finalization_run_id' => $run->id,
                    'result_finalized_by' => $actorId,
                    'result_finalized_at' => $timestamp,
                    'summary' => [
                        ...$existing,
                        'finalization' => $summary,
                    ],
                ]);
            });

            $state->refresh();

            $this->audit->recordByActorId(
                'RESULT_FINALIZATION_COMPLETED',
                $actorId,
                $beforeStatus,
                PreliminaryProcessingStatus::ResultFinalized->value,
                $run->reason,
                $summary,
                $beforeSnapshot,
                $this->stateSnapshot($state),
                batchId: $state->latest_import_batch_id,
                processingRunId: $run->id,
            );

            return $run->refresh();
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'current_step' => 'Failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                'completed_at' => now(),
            ]);

            $state->update(['status' => PreliminaryProcessingStatus::CutoffSet->value]);

            $this->audit->recordByActorId(
                'RESULT_FINALIZATION_FAILED',
                $actorId,
                PreliminaryProcessingStatus::ResultFinalizing->value,
                PreliminaryProcessingStatus::CutoffSet->value,
                $run->reason,
                ['error' => $exception->getMessage()],
                processingRunId: $run->id,
                batchId: $state->latest_import_batch_id,
            );

            throw $exception;
        }
    }

    private function updateRowProgress(
        PreliminaryFinalizationRun $run,
        int $processedRows,
        int $totalRows,
        string $step,
    ): void {
        $percent = $totalRows > 0
            ? min(94.0, 8.0 + (($processedRows / $totalRows) * 86.0))
            : 94.0;

        $this->updateProgress($run, $percent, min($processedRows, $totalRows), $step);
    }

    private function updateProgress(
        PreliminaryFinalizationRun $run,
        float $percent,
        int $processedRows,
        string $step,
    ): void {
        $run->update([
            'current_step' => $step,
            'processed_rows' => $processedRows,
            'progress_percent' => round(max(0, min(100, $percent)), 2),
        ]);
    }

    /** @return array<string,mixed> */
    public function summary(): array
    {
        $rows = DB::connection('exam')->table('registrations as r')
            ->leftJoin('preliminary_results as p', 'p.registration_id', '=', 'r.id')
            ->where('r.status', 'active')
            ->selectRaw("r.cadre_category, CASE WHEN p.id IS NULL THEN 'absent' WHEN p.candidate_status IN ('cancelled','withheld','expelled') THEN p.candidate_status ELSE COALESCE(p.result_status, 'unprocessed') END as outcome, COUNT(*) as aggregate")
            ->groupBy('r.cadre_category', 'outcome')
            ->get();

        $summary = [];
        foreach (['pass', 'fail', 'cancelled', 'withheld', 'expelled', 'absent'] as $outcome) {
            $summary[$outcome] = ['total' => 0, 'GG' => 0, 'TT' => 0, 'GT' => 0];
        }

        foreach ($rows as $row) {
            if (! isset($summary[$row->outcome])) {
                continue;
            }
            $category = match ((int) $row->cadre_category) {
                1 => 'GG', 2 => 'TT', 3 => 'GT', default => null,
            };
            if ($category === null) {
                continue;
            }
            $count = (int) $row->aggregate;
            $summary[$row->outcome][$category] += $count;
            $summary[$row->outcome]['total'] += $count;
        }

        return $summary;
    }

    /** @return array<string,mixed> */
    private function stateSnapshot(PreliminaryProcessingState $state): array
    {
        return [
            'status' => $state->status instanceof \BackedEnum ? $state->status->value : $state->status,
            'latest_import_batch_id' => $state->latest_import_batch_id,
            'latest_reconciliation_report_id' => $state->latest_reconciliation_report_id,
            'latest_distribution_report_id' => $state->latest_distribution_report_id,
            'current_cutoff_decision_id' => $state->current_cutoff_decision_id,
            'latest_finalization_run_id' => $state->latest_finalization_run_id,
            'cutoff_mark' => $state->cutoff_mark,
            'cutoff_requires_review' => $state->cutoff_requires_review,
            'result_finalized_by' => $state->result_finalized_by,
            'result_finalized_at' => optional($state->result_finalized_at)->toDateTimeString(),
            'summary' => $state->summary,
        ];
    }
}
