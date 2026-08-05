<?php

namespace App\Jobs;

use App\Models\Examination;
use App\Models\User;
use App\Services\Viva\VivaAuditService;
use App\Services\Viva\VivaReconciliationService;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessVivaReconciliation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 0;

    public function __construct(
        public int $examinationId,
        public int $runId,
        public int $actorId,
    ) {
        $this->onQueue((string) config('viva.queue', 'imports'));
    }

    public function handle(
        ExaminationConnectionManager $connections,
        VivaReconciliationService $service,
        VivaAuditService $audit,
    ): void {
        $connections->configure(Examination::findOrFail($this->examinationId));
        try {
            $run = $service->process($this->runId, $this->actorId);
            $audit->record(
                'VIVA_RECONCILIATION_GENERATED',
                User::findOrFail($this->actorId),
                'board_data_imported',
                'reconciliation_generated',
                'Viva reconciliation and review flags regenerated.',
                summary: [
                    'run_id' => $run->id,
                    'eligible' => $run->eligible_count,
                    'board_data' => $run->board_data_count,
                    'warnings' => $run->warning_count,
                    'quota_mismatches' => $run->quota_mismatch_count,
                    'high_mark_review' => $run->high_mark_count,
                ],
            );
        } finally {
            $connections->disconnect();
        }
    }
}
