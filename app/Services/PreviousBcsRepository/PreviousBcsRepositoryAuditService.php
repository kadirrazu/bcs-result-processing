<?php

namespace App\Services\PreviousBcsRepository;

use App\Models\PreviousBcsRepositoryAudit;

final class PreviousBcsRepositoryAuditService
{
    public function record(
        string $action,
        ?int $repositoryId,
        ?int $datasetId,
        ?int $actorId,
        array $context = [],
    ): void {
        PreviousBcsRepositoryAudit::query()->create([
            'repository_id' => $repositoryId,
            'dataset_id' => $datasetId,
            'action' => $action,
            'actor_id' => $actorId,
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }
}
