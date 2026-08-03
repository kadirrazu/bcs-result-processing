<?php

namespace App\Services\Viva;

use App\Models\User;
use App\Models\VivaProcessingAudit;
use Illuminate\Support\Facades\Log;

/** Writes synchronized Viva audit entries to the examination database and daily file log. */
final class VivaAuditService
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
        ?int $registrationId = null,
        ?int $vivaResultId = null,
    ): VivaProcessingAudit {
        $ip = app()->runningInConsole() ? null : request()->ip();
        $userAgent = app()->runningInConsole() ? null : request()->userAgent();

        $context = [
            'action' => $action,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'batch_id' => $batchId,
            'registration_id' => $registrationId,
            'viva_result_id' => $vivaResultId,
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

        $audit = VivaProcessingAudit::query()->create([
            ...$context,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
        ]);

        Log::channel('viva')->info('Viva processing action recorded.', $context);

        return $audit;
    }
}
