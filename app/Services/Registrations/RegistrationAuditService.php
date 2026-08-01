<?php

namespace App\Services\Registrations;

use App\Models\Registration;
use App\Models\RegistrationAudit;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Persist synchronized database and daily-file audit trails for manual registration edits. */
final class RegistrationAuditService
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param array<string, array{before:mixed,after:mixed}> $changedFields
     */
    public function recordManualUpdate(
        Registration $registration,
        User $actor,
        string $reason,
        array $before,
        array $after,
        array $changedFields,
    ): RegistrationAudit {
        $ip = app()->runningInConsole() ? null : request()->ip();
        $userAgent = app()->runningInConsole() ? null : request()->userAgent();

        $context = [
            'action' => 'REGISTRATION_MANUAL_UPDATED',
            'registration_id' => (int) $registration->getKey(),
            'reg' => (string) $registration->reg,
            'user_id' => (string) $registration->user_id,
            'actor_id' => (int) $actor->id,
            'actor_name' => (string) $actor->name,
            'reason' => $reason,
            'changed_fields' => $changedFields,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ];

        $audit = RegistrationAudit::query()->create([
            'registration_id' => $registration->getKey(),
            'action' => $context['action'],
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'reason' => $reason,
            'changed_fields' => $changedFields,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);

        Log::channel('registration')->info('Registration manual correction recorded.', $context);

        return $audit;
    }
}
