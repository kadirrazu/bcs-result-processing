<?php

namespace App\Services\Written;

use App\Models\User;
use App\Models\WrittenProcessingAudit;
use Illuminate\Support\Facades\Log;

/** Synchronized examination-database and daily-file Written audit writer. */
final class WrittenAuditService
{
    public function record(
        string $action,
        User $actor,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $reason = null,
        array $changedFields = [],
        array $summary = [],
        ?array $before = null,
        ?array $after = null,
        ?int $batchId = null,
        ?int $processingRunId = null,
        ?int $registrationId = null,
        ?int $writtenResultId = null,
    ): WrittenProcessingAudit {
        $ip = app()->runningInConsole() ? null : request()->ip();
        $userAgent = app()->runningInConsole() ? null : request()->userAgent();

        $context = [
            'action' => $action,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'batch_id' => $batchId,
            'processing_run_id' => $processingRunId,
            'registration_id' => $registrationId,
            'written_result_id' => $writtenResultId,
            'actor_id' => (int) $actor->id,
            'actor_name' => (string) $actor->name,
            'reason' => $reason,
            'changed_fields' => $changedFields,
            'summary' => $summary,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ];

        $audit = WrittenProcessingAudit::query()->create([
            ...$context,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
        ]);

        Log::channel('written')->info('Written processing action recorded.', $context);

        return $audit;
    }
}
