<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\Written\WrittenAuditService;
use App\Services\Written\WrittenImportService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessWrittenImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly int $batchId, public readonly int $actorId)
    {
        $this->onQueue((string) config('written.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, WrittenImportService $service, WrittenAuditService $audit): void
    {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);
        try {
            $batch = $service->process($this->batchId);
            $audit->recordByActorId('WRITTEN_IMPORT_STAGED', $this->actorId, 'queued', 'staged', null, [
                'staged_rows' => (int) $batch->staged_rows, 'original_name' => $batch->original_name,
            ], $batch->id);
        } finally {
            $connections->disconnect();
        }
    }

    public function failed(?Throwable $exception): void { report($exception); }
}
