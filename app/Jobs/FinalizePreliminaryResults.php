<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\Preliminary\PreliminaryFinalizationService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class FinalizePreliminaryResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $runId,
        public readonly int $actorId,
    ) {
        $this->onQueue((string) config('preliminary.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        PreliminaryFinalizationService $service,
    ): void {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);

        try {
            $service->finalize($this->runId, $this->actorId);
        } finally {
            $connections->disconnect();
        }
    }
}
