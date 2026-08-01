<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\Registrations\RegistrationStagingValidationService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ValidateRegistrationImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly int $batchId)
    {
        $this->onQueue((string) config('registrations.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, RegistrationStagingValidationService $service): void
    {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);
        try {
            $service->validate($this->batchId);
        } finally {
            $connections->disconnect();
        }
    }
}
