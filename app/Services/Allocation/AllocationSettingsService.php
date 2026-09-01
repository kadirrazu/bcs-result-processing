<?php

namespace App\Services\Allocation;

use App\Models\AllocationProcessingAudit;
use App\Models\AllocationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AllocationSettingsService
{
    public function setting(): AllocationSetting
    {
        return AllocationSetting::query()->firstOrCreate(['id' => 1], [
            'quota_priority' => config('allocation.default_quota_priority', ['CFF', 'EM', 'PHC']),
            'small_cadre_quota_threshold' => (int) config('allocation.small_cadre_quota_threshold', 10),
            'mq_percent' => 93, 'cff_percent' => 5, 'em_percent' => 1, 'phc_percent' => 1,
            'status' => 'draft',
        ]);
    }

    public function hash(AllocationSetting $setting): string
    {
        $payload = [
            'quota_priority' => array_values((array) $setting->quota_priority),
            'small_cadre_quota_threshold' => (int) $setting->small_cadre_quota_threshold,
            'mq_percent' => (int) $setting->mq_percent,
            'cff_percent' => (int) $setting->cff_percent,
            'em_percent' => (int) $setting->em_percent,
            'phc_percent' => (int) $setting->phc_percent,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function finalize(?int $actorId): AllocationSetting
    {
        return DB::connection('exam')->transaction(function () use ($actorId): AllocationSetting {
            $setting = AllocationSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            $priority = array_values(array_unique(array_map('strtoupper', (array) $setting->quota_priority)));
            if ($priority !== array_values(array_intersect($priority, ['CFF', 'EM', 'PHC'])) || count($priority) !== 3) {
                throw ValidationException::withMessages(['quota_priority' => 'Quota priority must contain CFF, EM and PHC exactly once.']);
            }
            if ((int) $setting->small_cadre_quota_threshold !== 10) {
                throw ValidationException::withMessages(['small_cadre_quota_threshold' => 'Locked Allocation rule requires quota breakup only when total post is 10 or more.']);
            }
            if ((int) $setting->mq_percent + (int) $setting->cff_percent + (int) $setting->em_percent + (int) $setting->phc_percent !== 100) {
                throw ValidationException::withMessages(['percentages' => 'Provisional breakup percentages must total 100.']);
            }

            $hash = $this->hash($setting);
            $from = (string) $setting->status;
            $setting->forceFill([
                'status' => 'finalized', 'settings_hash' => $hash,
                'finalized_by' => $actorId, 'finalized_at' => now(),
            ])->save();

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_SETTINGS_FINALIZED', 'actor_id' => $actorId,
                'from_status' => $from, 'to_status' => 'finalized',
                'context' => ['settings_hash' => $hash], 'created_at' => now(),
            ]);

            return $setting->refresh();
        });
    }

    public function verified(): AllocationSetting
    {
        $setting = $this->setting();
        if ((string) $setting->status !== 'finalized' || ! $setting->settings_hash) {
            throw ValidationException::withMessages(['allocation_settings' => 'Allocation Settings must be finalized/frozen before Allocation.']);
        }
        if (! hash_equals((string) $setting->settings_hash, $this->hash($setting))) {
            throw ValidationException::withMessages(['allocation_settings' => 'ALLOCATION_SETTINGS_HASH_MISMATCH: Settings changed after finalization. Finalize settings again.']);
        }

        return $setting;
    }
}
