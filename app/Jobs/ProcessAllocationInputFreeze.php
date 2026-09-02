<?php

namespace App\Jobs;

use App\Models\AllocationProcessingState;
use App\Models\Examination;
use App\Services\Allocation\AllocationInputFreezeService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessAllocationInputFreeze implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly ?int $actorId)
    {
        $this->onQueue((string) config('allocation.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, AllocationInputFreezeService $service): void
    {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);

        try {
            AllocationProcessingState::query()->whereKey(1)->update([
                'status' => 'input_freeze_running',
                'phase' => 'VERIFYING_INPUTS',
                'progress_percent' => 2,
                'progress_message' => 'Strictly verifying authoritative inputs…',
                'last_error' => null,
            ]);

            $service->freeze($this->actorId, function (string $phase, int $percent, string $message, int $current = 0, int $total = 0): void {
                AllocationProcessingState::query()->whereKey(1)->update([
                    'status' => 'input_freeze_running',
                    'phase' => $phase,
                    'progress_percent' => max(0, min(99, $percent)),
                    'progress_current' => max(0, $current),
                    'progress_total' => max(0, $total),
                    'progress_message' => $message,
                ]);
            });
        } catch (Throwable $e) {
            AllocationProcessingState::query()->whereKey(1)->update([
                'status' => 'input_freeze_failed',
                'phase' => 'FAILED',
                'progress_message' => 'Allocation input freeze failed.',
                'last_error' => mb_substr($e->getMessage(), 0, 65000),
            ]);
            throw $e;
        } finally {
            $connections->disconnect();
        }
    }
}
