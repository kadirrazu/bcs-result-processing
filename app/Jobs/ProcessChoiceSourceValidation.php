<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\ChoiceValidation\ChoiceSourceValidationService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessChoiceSourceValidation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $batchId,
        public readonly int $actorId,
    ) {
        $this->onQueue((string) config('choice-validation.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        ChoiceSourceValidationService $service,
    ): void {
        $exam = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($exam);

        try {
            $service->validate($this->batchId);
        } finally {
            $connections->disconnect();
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
