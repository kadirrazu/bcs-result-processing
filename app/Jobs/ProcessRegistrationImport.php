<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\Registrations\RegistrationImportService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Process one registration spreadsheet outside the HTTP request lifecycle. */
final class ProcessRegistrationImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $batchId,
    ) {
        $this->onQueue((string) config('registrations.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        RegistrationImportService $service,
    ): void {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);

        try {
            $service->process($this->batchId);
        } finally {
            $connections->disconnect();
        }
    }

    public function failed(?Throwable $exception): void
    {
        // The service records detailed failures whenever the examination connection is available.
        report($exception);
    }
}
