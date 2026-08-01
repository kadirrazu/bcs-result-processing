<?php

namespace App\Services\Preliminary;

use App\Models\PreliminaryProcessingAudit;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Writes synchronized examination-database and daily-file audit records. */
final class PreliminaryAuditService
{
    public function record(
        string $action,
        User $actor,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $reason = null,
        array $summary = [],
        ?array $before = null,
        ?array $after = null,
        ?int $batchId = null,
        ?int $processingRunId = null,
    ): PreliminaryProcessingAudit {
        return $this->write(
            $action, (int) $actor->id, (string) $actor->name, $statusBefore, $statusAfter,
            $reason, $summary, $before, $after, $batchId, $processingRunId,
        );
    }

    public function recordByActorId(
        string $action,
        int $actorId,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $reason = null,
        array $summary = [],
        ?array $before = null,
        ?array $after = null,
        ?int $batchId = null,
        ?int $processingRunId = null,
    ): PreliminaryProcessingAudit {
        $actor = User::query()->find($actorId);

        return $this->write(
            $action, $actorId, $actor?->name, $statusBefore, $statusAfter,
            $reason, $summary, $before, $after, $batchId, $processingRunId,
        );
    }

    private function write(
        string $action,
        int $actorId,
        ?string $actorName,
        ?string $statusBefore,
        ?string $statusAfter,
        ?string $reason,
        array $summary,
        ?array $before,
        ?array $after,
        ?int $batchId,
        ?int $processingRunId,
    ): PreliminaryProcessingAudit {
        $ip = null;
        $userAgent = null;
        if (! app()->runningInConsole()) {
            $ip = request()->ip();
            $userAgent = request()->userAgent();
        }

        $context = [
            'action' => $action,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'batch_id' => $batchId,
            'processing_run_id' => $processingRunId,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'reason' => $reason,
            'summary' => $summary,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ];

        $audit = PreliminaryProcessingAudit::query()->create([
            ...$context,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        Log::channel('preliminary')->info('Preliminary processing action recorded.', $context);

        return $audit;
    }
}
