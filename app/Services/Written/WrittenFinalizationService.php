<?php

namespace App\Services\Written;

use App\Enums\WrittenProcessingStatus;
use App\Models\User;
use App\Models\WrittenProcessingRun;
use App\Models\WrittenProcessingState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Freezes the current, reviewed Written result snapshot without recalculating marks. */
final class WrittenFinalizationService
{
    public function __construct(private readonly WrittenAuditService $audit) {}

    public function finalize(int $runId, int $actorId, string $reason): WrittenProcessingRun
    {
        $run = WrittenProcessingRun::query()->findOrFail($runId);
        $state = WrittenProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => WrittenProcessingStatus::NotStarted->value],
        );

        if ($run->type !== 'written_finalization' || ! in_array($run->status, ['queued', 'failed'], true)) {
            throw new RuntimeException('This finalization request is no longer available to run.');
        }
        if ((bool) $state->is_stale) {
            throw new RuntimeException('Written data changed after processing. Regenerate reconciliation and process Written rules before finalizing.');
        }
        if ($state->reconciliation_generated_at === null || $state->paper_crash_processed_at === null) {
            throw new RuntimeException('Complete reconciliation and Written rule processing before finalizing the result.');
        }

        $stateValue = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
        if (! in_array($stateValue, [WrittenProcessingStatus::ProcessingReady->value, WrittenProcessingStatus::ResultFinalized->value], true)) {
            throw new RuntimeException('The Written result is not ready for final review yet.');
        }

        $this->assertProcessedFactsAreComplete();
        $actor = User::query()->findOrFail($actorId);
        $before = $this->stateSnapshot($state);
        $timestamp = now();
        $totalRows = DB::connection('exam')->table('written_results')->count();

        $run->update([
            'status' => 'running',
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'progress_percent' => 2,
            'current_step' => 'Preparing the reviewed Written result',
            'failure_message' => null,
            'started_at' => $timestamp,
            'finished_at' => null,
        ]);

        try {
            $processed = 0;
            DB::connection('exam')->table('written_results')
                ->select('id')
                ->orderBy('id')
                ->chunkById(2000, function ($rows) use (&$processed, $totalRows, $timestamp, $run): void {
                    $ids = $rows->pluck('id')->map(static fn ($id): int => (int) $id)->all();
                    if ($ids === []) {
                        return;
                    }
                    DB::connection('exam')->table('written_results')->whereIn('id', $ids)->update([
                        'finalized_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                    $processed += count($ids);
                    $percent = $totalRows > 0 ? min(94.0, 5.0 + (($processed / $totalRows) * 89.0)) : 94.0;
                    $run->update([
                        'processed_rows' => min($processed, $totalRows),
                        'progress_percent' => round($percent, 2),
                        'current_step' => 'Locking the reviewed candidate results',
                    ]);
                }, 'id');

            $run->update(['progress_percent' => 96, 'current_step' => 'Preparing the final result summary']);
            $summary = $this->summary();

            DB::connection('exam')->transaction(function () use ($state, $run, $summary, $actorId, $timestamp, $totalRows): void {
                $existing = is_array($state->summary) ? $state->summary : [];
                $state->update([
                    'status' => WrittenProcessingStatus::ResultFinalized->value,
                    'result_finalized_at' => $timestamp,
                    'result_finalized_by' => $actorId,
                    'is_stale' => false,
                    'stale_reason' => null,
                    'summary' => [...$existing, 'finalization' => $summary],
                ]);
                $run->update([
                    'status' => 'completed',
                    'processed_rows' => $totalRows,
                    'progress_percent' => 100,
                    'current_step' => 'Final result is ready',
                    'finished_at' => $timestamp,
                ]);
            });

            $state->refresh();
            $this->audit->record(
                'WRITTEN_RESULT_FINALIZED',
                $actor,
                $before['status'] ?? null,
                WrittenProcessingStatus::ResultFinalized->value,
                $reason,
                summary: $summary,
                before: $before,
                after: $this->stateSnapshot($state),
                batchId: $state->latest_import_batch_id,
                processingRunId: $run->id,
            );

            return $run->refresh();
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'current_step' => 'Finalization needs attention',
                'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);
            $this->audit->record(
                'WRITTEN_RESULT_FINALIZATION_FAILED',
                $actor,
                $stateValue,
                $stateValue,
                $reason,
                summary: ['error' => $exception->getMessage()],
                batchId: $state->latest_import_batch_id,
                processingRunId: $run->id,
            );
            throw $exception;
        }
    }

    private function assertProcessedFactsAreComplete(): void
    {
        $query = DB::connection('exam')->table('written_results')->where('status', 'active')->where(function ($q): void {
            $q->where(function ($x): void {
                $x->whereIn('cadre_category', [1, 3])->whereNull('general_result_status');
            })->orWhere(function ($x): void {
                $x->whereIn('cadre_category', [2, 3])->whereNull('technical_result_status');
            });
        });

        if ($query->exists()) {
            throw new RuntimeException('Some active candidates still have unprocessed track results. Reprocess Written rules before finalizing.');
        }
    }

    /** @return array<string,mixed> */
    private function summary(): array
    {
        $base = DB::connection('exam')->table('written_results');
        $qualified = [];
        foreach (['GG', 'GN', 'TT', 'T', 'GT'] as $track) {
            $qualified[$track] = (clone $base)->where('status', 'active')->where('written_qualified_track', $track)->count();
        }

        $reconciliation = DB::connection('exam')->table('written_reconciliation_reports')->latest('id')->value('summary');
        $reconciliation = is_string($reconciliation) ? (json_decode($reconciliation, true) ?: []) : (array) ($reconciliation ?? []);

        return [
            'qualified_total' => array_sum($qualified),
            'failed_total' => (clone $base)->where('status', 'active')->whereNull('written_qualified_track')->count(),
            'cancelled_total' => (clone $base)->where('status', 'cancelled')->count(),
            'withheld_total' => (clone $base)->where('status', 'withheld')->count(),
            'qualified_tracks' => $qualified,
            'effective_categories' => [
                'GG' => $qualified['GG'] + $qualified['GN'],
                'TT' => $qualified['TT'] + $qualified['T'],
                'GT' => $qualified['GT'],
            ],
            'eligible' => (int) data_get($reconciliation, 'eligible.total', 0),
            'appeared' => (int) data_get($reconciliation, 'appeared.total', 0),
            'completely_absent' => (int) data_get($reconciliation, 'completely_absent.total', 0),
        ];
    }

    /** @return array<string,mixed> */
    private function stateSnapshot(WrittenProcessingState $state): array
    {
        return [
            'status' => $state->status instanceof \BackedEnum ? $state->status->value : $state->status,
            'latest_import_batch_id' => $state->latest_import_batch_id,
            'latest_reconciliation_report_id' => $state->latest_reconciliation_report_id,
            'latest_processing_run_id' => $state->latest_processing_run_id,
            'paper_crash_processed_at' => $state->paper_crash_processed_at?->toDateTimeString(),
            'result_finalized_at' => $state->result_finalized_at?->toDateTimeString(),
            'result_finalized_by' => $state->result_finalized_by,
            'is_stale' => (bool) $state->is_stale,
            'stale_reason' => $state->stale_reason,
            'summary' => $state->summary,
        ];
    }
}
