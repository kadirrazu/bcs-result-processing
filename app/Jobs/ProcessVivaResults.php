<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Models\User;
use App\Services\Viva\VivaAuditService;
use App\Services\Viva\VivaResultProcessingService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessVivaResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $runId,
        public readonly int $actorId,
    ) {
        $this->onQueue((string) config('viva.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        VivaResultProcessingService $service,
        VivaAuditService $audit,
    ): void {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);

        try {
            $run = $service->process($this->runId, $this->actorId);
            $actor = User::query()->findOrFail($this->actorId);
            $audit->record(
                'VIVA_RESULT_PROCESSING_COMPLETED',
                $actor,
                'processing_running',
                'processing_completed',
                summary: $run->summary ?? [],
            );
        } catch (Throwable $exception) {
            $actor = User::query()->find($this->actorId);
            if ($actor !== null) {
                $audit->record(
                    'VIVA_RESULT_PROCESSING_FAILED',
                    $actor,
                    'processing_running',
                    'reconciliation_generated',
                    $exception->getMessage(),
                    summary: ['run_id' => $this->runId],
                );
            }
            throw $exception;
        } finally {
            $connections->disconnect();
        }
    }
}
