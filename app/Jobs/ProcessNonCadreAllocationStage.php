<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\NonCadre\Allocation\NonCadreAllocationService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessNonCadreAllocationStage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $runId,
        public readonly string $stage,
        public readonly ?int $actorId,
    ) {
        $this->onQueue((string) config('non-cadre.allocation.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, NonCadreAllocationService $service): void
    {
        $connections->configure(Examination::query()->findOrFail($this->examinationId));
        try {
            $service->processQueuedStage($this->runId, $this->stage, $this->actorId);
        } catch (Throwable $e) {
            $service->markStageFailed($this->runId, $this->stage, $e->getMessage());
            throw $e;
        } finally {
            $connections->disconnect();
        }
    }
}
