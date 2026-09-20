<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\NonCadre\Choice\NonCadreChoiceService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessNonCadreChoiceImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $importId,
        public readonly ?int $actorId,
    ) {
        $this->onQueue((string) config('non-cadre.choice.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, NonCadreChoiceService $service): void
    {
        $connections->configure(Examination::query()->findOrFail($this->examinationId));
        try {
            $service->processQueuedImport($this->importId, $this->actorId);
        } finally {
            $connections->disconnect();
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
