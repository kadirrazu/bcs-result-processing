<?php

namespace App\Jobs;

use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\AllocationRun;
use App\Models\Examination;
use App\Services\Allocation\AllocationPhaseOneService;
use App\Services\Allocation\AllocationRunStaleService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Queue wrapper for A3. The engine lives in AllocationPhaseOneService. */
final class ProcessAllocationPhaseOne implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $allocationRunId,
        public readonly ?int $actorId,
    ) {
        $this->onQueue((string) config('allocation.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, AllocationPhaseOneService $service, AllocationRunStaleService $stale): void
    {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);

        try {
            $run = AllocationRun::query()->findOrFail($this->allocationRunId);
            $run->forceFill([
                'status' => 'running',
                'phase' => 'VERIFYING_INPUT',
                'started_at' => $run->started_at ?: now(),
                'failure_message' => null,
            ])->save();

            AllocationProcessingState::query()->whereKey(1)->update([
                'status' => 'phase1_running',
                'phase' => 'VERIFYING_INPUT',
                'progress_percent' => 2,
                'progress_current' => 0,
                'progress_total' => 0,
                'progress_message' => 'Strictly verifying frozen Allocation input before Phase-1.',
                'last_error' => null,
            ]);

            $service->process($run, function (string $phase, int $percent, string $message, int $current = 0, int $total = 0) use ($run): void {
                AllocationRun::query()->whereKey($run->id)->update(['phase' => $phase]);
                AllocationProcessingState::query()->whereKey(1)->update([
                    'status' => 'phase1_running',
                    'phase' => $phase,
                    'progress_percent' => max(0, min(99, $percent)),
                    'progress_current' => max(0, $current),
                    'progress_total' => max(0, $total),
                    'progress_message' => $message,
                ]);
            });

            $completed = AllocationRun::query()->findOrFail($run->id);
            if ((string) $completed->status === 'phase1_complete' && ! (bool) $completed->is_stale) {
                $stale->supersedeEarlierA3ForNewA3($completed, $this->actorId);
                $stale->staleA4ForNewA3($completed, $this->actorId);
            }
        } catch (Throwable $e) {
            AllocationRun::query()->whereKey($this->allocationRunId)->update([
                'status' => 'failed',
                'phase' => 'FAILED',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'completed_at' => now(),
            ]);
            AllocationProcessingState::query()->whereKey(1)->update([
                'status' => 'phase1_failed',
                'phase' => 'FAILED',
                'progress_message' => 'Allocation Phase-1 failed. No Phase-1 result was committed.',
                'last_error' => mb_substr($e->getMessage(), 0, 65000),
            ]);
            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_PHASE1_FAILED',
                'actor_id' => $this->actorId,
                'from_status' => 'phase1_running',
                'to_status' => 'phase1_failed',
                'context' => [
                    'allocation_run_id' => $this->allocationRunId,
                    'error' => mb_substr($e->getMessage(), 0, 4000),
                ],
                'created_at' => now(),
            ]);
            throw $e;
        } finally {
            $connections->disconnect();
        }
    }
}
