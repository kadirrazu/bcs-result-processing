<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\Preliminary\PreliminaryAuditService;
use App\Services\Preliminary\PreliminaryImportService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessPreliminaryImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public readonly int $examinationId,
        public readonly int $batchId,
        public readonly int $actorId,
    ) {
        $this->onQueue((string) config('preliminary.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        PreliminaryImportService $service,
        PreliminaryAuditService $audit,
    ): void {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);

        try {
            $batch = $service->process($this->batchId);
            $audit->recordByActorId(
                'MARK_IMPORT_STAGED', $this->actorId, 'queued', 'staged', null,
                ['staged_rows' => (int) $batch->staged_rows, 'original_name' => $batch->original_name],
                batchId: $batch->id,
            );
        } finally {
            $connections->disconnect();
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
