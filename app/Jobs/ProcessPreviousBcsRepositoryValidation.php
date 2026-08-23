<?php

namespace App\Jobs;

use App\Services\PreviousBcsRepository\PreviousBcsRepositoryValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessPreviousBcsRepositoryValidation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $datasetId,
        public readonly int $actorId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(PreviousBcsRepositoryValidationService $service): void
    {
        $service->validate($this->datasetId, $this->actorId);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
