<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Models\User;
use App\Services\Written\WrittenAuditService;
use App\Services\Written\WrittenRuleProcessingService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessWrittenRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(public readonly int $examinationId, public readonly int $runId, public readonly int $actorId)
    {
        $this->onQueue((string) config('written.queue', 'imports'));
    }

    public function handle(ExaminationConnectionManager $connections, WrittenRuleProcessingService $service, WrittenAuditService $audit): void
    {
        $examination = Examination::query()->findOrFail($this->examinationId);
        $connections->configure($examination);
        try {
            $run = $service->process($this->runId, $this->actorId);
            $actor = User::query()->findOrFail($this->actorId);
            $audit->record('WRITTEN_RULE_PROCESSING_COMPLETED', $actor, 'reconciliation_generated', 'processing_ready', null,
                summary: ['processed_rows' => (int) $run->processed_rows], processingRunId: $run->id);
        } catch (Throwable $e) {
            $actor = User::query()->find($this->actorId);
            if ($actor !== null) {
                $audit->record('WRITTEN_RULE_PROCESSING_FAILED', $actor, null, null, $e->getMessage(), processingRunId: $this->runId);
            }
            throw $e;
        } finally {
            $connections->disconnect();
        }
    }
}
