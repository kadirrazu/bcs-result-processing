<?php

namespace App\Services\MasterData;

use App\Models\MasterDataAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class MasterDataAuditService
{
    public function record(string $action, Model $entity, User $actor, ?string $reason, ?array $before, ?array $after): MasterDataAudit
    {
        $changed = [];
        if ($before !== null && $after !== null) {
            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
                if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                    $changed[] = $field;
                }
            }
        }

        $context = [
            'entity_type' => $entity::class,
            'entity_id' => (int) $entity->getKey(),
            'action' => $action,
            'actor_id' => (int) $actor->id,
            'actor_name' => (string) $actor->name,
            'reason' => $reason,
            'changed_fields' => $changed,
            'before' => $before,
            'after' => $after,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
        ];

        $audit = MasterDataAudit::query()->create([
            'module' => 'master_data',
            'entity_type' => $context['entity_type'],
            'entity_id' => $context['entity_id'],
            'action' => $action,
            'actor_id' => $context['actor_id'],
            'actor_name' => $context['actor_name'],
            'reason' => $reason,
            'changed_fields' => $changed,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'ip_address' => $context['ip_address'],
            'user_agent' => $context['user_agent'],
            'created_at' => now(),
        ]);

        Log::channel('master_data')->info('Master data change recorded.', $context);

        return $audit;
    }
}
