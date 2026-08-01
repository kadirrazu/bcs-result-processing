<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Services\Written\WrittenApprovalService;
use App\Services\Written\WrittenAuditService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ApproveWrittenImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly int $batchId, public readonly int $actorId)
    {
        $this->onQueue((string) config('written.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, WrittenApprovalService $service, WrittenAuditService $audit): void
    {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);
        try {
            $batch = $service->approve($this->batchId, $this->actorId);
            $audit->recordByActorId('WRITTEN_IMPORT_APPROVED', $this->actorId, 'validated', 'marks_imported', null, [
                'approved_rows' => (int) $batch->approved_rows, 'inserted_rows' => (int) $batch->inserted_rows,
                'updated_rows' => (int) $batch->updated_rows,
            ], $batch->id);
        } finally {
            $connections->disconnect();
        }
    }
}
