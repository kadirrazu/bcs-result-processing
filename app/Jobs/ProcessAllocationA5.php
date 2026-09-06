<?php

namespace App\Jobs;

use App\Models\AllocationA5Run;
use App\Models\AllocationProcessingAudit;
use App\Models\Examination;
use App\Services\Allocation\AllocationA5ValidityService;
use App\Services\Allocation\AllocationRunStaleService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessAllocationA5 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly int $a5RunId, public readonly ?int $actorId)
    {
        $this->onQueue((string) config('allocation.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        AllocationA5ValidityService $service,
        AllocationRunStaleService $stale,
    ): void
    {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);
        try {
            $run = AllocationA5Run::query()->findOrFail($this->a5RunId);
            $run->forceFill([
                'status'=>'running','phase'=>'VERIFYING_SOURCES','progress_percent'=>2,
                'progress_message'=>'Verifying current A4, Circular and Registration authority.','started_at'=>$run->started_at ?: now(),
                'failure_message'=>null,
            ])->save();
            $service->process($run, function (string $phase, int $percent, string $message, int $current = 0, int $total = 0) use ($run): void {
                AllocationA5Run::query()->whereKey($run->id)->update([
                    'phase'=>$phase,'progress_percent'=>max(0,min(99,$percent)),'progress_current'=>max(0,$current),
                    'progress_total'=>max(0,$total),'progress_message'=>$message,
                ]);
            });

            $completed = AllocationA5Run::query()->findOrFail($run->id);
            if (in_array((string) $completed->status, ['validated_ok','validated_failed'], true)
                && ! (bool) $completed->is_stale) {
                $stale->supersedeEarlierA5ForNewA5($completed, $this->actorId);
            }
        } catch (Throwable $e) {
            AllocationA5Run::query()->whereKey($this->a5RunId)->update([
                'status'=>'failed','phase'=>'FAILED','failure_message'=>mb_substr($e->getMessage(),0,65000),
                'progress_message'=>'A5 validity check failed. A4 allocation remains unchanged.','completed_at'=>now(),
            ]);
            AllocationProcessingAudit::query()->create([
                'event'=>'ALLOCATION_A5_FAILED','actor_id'=>$this->actorId,'from_status'=>'running','to_status'=>'failed',
                'context'=>['allocation_a5_run_id'=>$this->a5RunId,'error'=>mb_substr($e->getMessage(),0,4000)],'created_at'=>now(),
            ]);
            throw $e;
        } finally {
            $connections->disconnect();
        }
    }
}
