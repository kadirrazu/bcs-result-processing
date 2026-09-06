<?php

namespace App\Jobs;

use App\Models\AllocationA4Run;
use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\Examination;
use App\Services\Allocation\AllocationA4Service;
use App\Services\Allocation\AllocationReadinessService;
use App\Services\Allocation\AllocationRunStaleService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Queue wrapper for A4. Business logic remains in AllocationA4Service. */
final class ProcessAllocationA4 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $a4RunId,
        public readonly ?int $actorId,
    ) {
        $this->onQueue((string) config('allocation.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        AllocationA4Service $service,
        AllocationReadinessService $readiness,
        AllocationRunStaleService $stale,
    ): void
    {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);

        try {
            $run = AllocationA4Run::query()->findOrFail($this->a4RunId);
            $run->forceFill([
                'status' => 'running', 'phase' => 'STRICT_PRE_RUN_GATE', 'progress_percent' => 1,
                'progress_message' => 'Strictly verifying current finalized Allocation inputs.',
                'started_at' => $run->started_at ?: now(), 'failure_message' => null,
            ])->save();

            AllocationProcessingState::query()->whereKey(1)->update([
                'status' => 'a4_running', 'phase' => 'STRICT_PRE_RUN_GATE', 'progress_percent' => 1,
                'progress_current' => 0, 'progress_total' => 0,
                'progress_message' => 'Strictly verifying current finalized Allocation inputs.', 'last_error' => null,
            ]);

            // Expensive full hash verification belongs in the queue worker, not
            // in the browser request that creates/dispatches the A4 run.
            $gate = $readiness->inspectStrict();
            if (! (bool) ($gate['ready'] ?? false)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocation' => 'Allocation strict pre-run gate failed inside the A4 worker. Refresh Allocation and resolve the stale/hash-mismatch input before re-running A4.',
                ]);
            }

            $run->forceFill([
                'phase' => 'VERIFYING_A3', 'progress_percent' => 2,
                'progress_message' => 'Verifying exact A3 source and frozen A2 input.',
            ])->save();
            AllocationProcessingState::query()->whereKey(1)->update([
                'status' => 'a4_running', 'phase' => 'VERIFYING_A3', 'progress_percent' => 2,
                'progress_current' => 0, 'progress_total' => 0,
                'progress_message' => 'Verifying exact A3 source and frozen A2 input.',
            ]);

            $service->process($run, function (string $phase, int $percent, string $message, int $current = 0, int $total = 0) use ($run): void {
                AllocationA4Run::query()->whereKey($run->id)->update([
                    'phase' => $phase, 'progress_percent' => max(0, min(99, $percent)),
                    'progress_current' => max(0, $current), 'progress_total' => max(0, $total),
                    'progress_message' => $message,
                ]);
                AllocationProcessingState::query()->whereKey(1)->update([
                    'status' => 'a4_running', 'phase' => $phase,
                    'progress_percent' => max(0, min(99, $percent)), 'progress_current' => max(0, $current),
                    'progress_total' => max(0, $total), 'progress_message' => $message,
                ]);
            });

            // Only a successfully completed new A4 becomes current. Preserve
            // older A4 evidence, but mark it historical/superseded so exactly
            // one completed A4 remains the current authority.
            $completed = AllocationA4Run::query()->findOrFail($run->id);
            if ((string) $completed->status === 'a4_complete' && ! (bool) $completed->is_stale) {
                $stale->supersedeEarlierA4ForNewA4($completed, $this->actorId);
                $stale->staleA5ForNewA4($completed, $this->actorId);
            }
        } catch (Throwable $e) {
            AllocationA4Run::query()->whereKey($this->a4RunId)->update([
                'status' => 'failed', 'phase' => 'FAILED', 'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'progress_message' => 'Allocation A4 failed. No A4 result was committed.', 'completed_at' => now(),
            ]);
            AllocationProcessingState::query()->whereKey(1)->update([
                'status' => 'a4_failed', 'phase' => 'FAILED',
                'progress_message' => 'Allocation A4 failed. A3 remains unchanged and no A4 result was committed.',
                'last_error' => mb_substr($e->getMessage(), 0, 65000),
            ]);
            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A4_FAILED', 'actor_id' => $this->actorId,
                'from_status' => 'a4_running', 'to_status' => 'a4_failed',
                'context' => ['allocation_a4_run_id' => $this->a4RunId, 'error' => mb_substr($e->getMessage(), 0, 4000)],
                'created_at' => now(),
            ]);
            throw $e;
        } finally {
            $connections->disconnect();
        }
    }
}
