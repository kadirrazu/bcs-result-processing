<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrApprovalService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessChoiceOptimizationOmrApproval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly int $batchId, public readonly int $actorId)
    {
        $this->onQueue((string) config('choice-optimization.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, ChoiceOptimizationOmrApprovalService $service): void
    {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);
        try {
            $service->approve($this->batchId, $this->actorId);
        } finally {
            $connections->disconnect();
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
