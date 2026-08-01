<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\Written\WrittenAuditService;
use App\Services\Written\WrittenValidationService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ValidateWrittenImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly int $batchId, public readonly int $actorId)
    {
        $this->onQueue((string) config('written.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, WrittenValidationService $service, WrittenAuditService $audit): void
    {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);
        try {
            $batch = $service->validate($this->batchId);
            $audit->recordByActorId('WRITTEN_IMPORT_VALIDATED', $this->actorId, 'staged', 'validated', null, [
                'valid_rows' => (int) $batch->valid_rows, 'warning_rows' => (int) $batch->warning_rows,
                'invalid_rows' => (int) $batch->invalid_rows, 'identity_conflict_rows' => (int) $batch->identity_conflict_rows,
            ], $batch->id);
        } finally {
            $connections->disconnect();
        }
    }
}
