<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\Registrations\RegistrationApprovalService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ApproveRegistrationImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $batchId,
        public readonly int $approvedBy,
    ) {
        $this->onQueue((string) config('registrations.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, RegistrationApprovalService $service): void
    {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);
        try {
            $service->approve($this->batchId, $this->approvedBy);
        } finally {
            $connections->disconnect();
        }
    }
}
