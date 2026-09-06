<?php

namespace App\Services\Allocation;

use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\AllocationSetting;
use App\Models\AllocationRun;
use App\Models\AllocationA4Run;
use App\Models\AllocationA5Run;
use App\Models\AllocationInputFreeze;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AllocationSettingsService
{
    /**
     * Current resolved file configuration. This is the editable source.
     * The allocation_settings row is only the frozen examination snapshot.
     */
    public function currentConfig(): array
    {
        $priority = array_values(array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            (array) config('allocation.quota_priority', ['CFF', 'EM', 'PHC'])
        ));

        $percentages = (array) config('allocation.provisional_breakup_percentages', [
            'mq' => 93,
            'cff' => 5,
            'em' => 1,
            'phc' => 1,
        ]);

        $resolved = [
            'quota_priority' => $priority,
            'quota_breakup_minimum_total_posts' => (int) config('allocation.quota_breakup_minimum_total_posts', 10),
            'mq_percent' => (int) ($percentages['mq'] ?? 0),
            'cff_percent' => (int) ($percentages['cff'] ?? 0),
            'em_percent' => (int) ($percentages['em'] ?? 0),
            'phc_percent' => (int) ($percentages['phc'] ?? 0),
        ];

        $this->validateResolvedConfig($resolved);

        return $resolved;
    }

    /**
     * Returns the frozen snapshot row. For an examination that has never frozen
     * settings, seed the draft row from the current file configuration.
     */
    public function setting(): AllocationSetting
    {
        $existing = AllocationSetting::query()->find(1);
        if ($existing) {
            return $existing;
        }

        $current = $this->currentConfig();

        return AllocationSetting::query()->create([
            'id' => 1,
            'quota_priority' => $current['quota_priority'],
            // Legacy DB column name retained for migration compatibility.
            'small_cadre_quota_threshold' => $current['quota_breakup_minimum_total_posts'],
            'mq_percent' => $current['mq_percent'],
            'cff_percent' => $current['cff_percent'],
            'em_percent' => $current['em_percent'],
            'phc_percent' => $current['phc_percent'],
            'status' => 'draft',
        ]);
    }

    public function currentConfigHash(): string
    {
        return $this->hashPayload($this->currentConfig());
    }

    public function hash(AllocationSetting $setting): string
    {
        return $this->hashPayload([
            'quota_priority' => array_values((array) $setting->quota_priority),
            'quota_breakup_minimum_total_posts' => (int) $setting->small_cadre_quota_threshold,
            'mq_percent' => (int) $setting->mq_percent,
            'cff_percent' => (int) $setting->cff_percent,
            'em_percent' => (int) $setting->em_percent,
            'phc_percent' => (int) $setting->phc_percent,
        ]);
    }

    public function dashboard(): array
    {
        $setting = $this->setting();
        $current = $this->currentConfig();
        $currentHash = $this->hashPayload($current);
        $frozenHash = (string) ($setting->settings_hash ?? '');
        $isFinalized = (string) $setting->status === 'finalized' && $frozenHash !== '';
        $matchesFrozen = $isFinalized && hash_equals($frozenHash, $currentHash);

        return [
            'snapshot' => $setting,
            'current' => $current,
            'current_hash' => $currentHash,
            'frozen_hash' => $frozenHash ?: null,
            'is_finalized' => $isFinalized,
            'matches_frozen' => $matchesFrozen,
            'needs_freeze' => ! $matchesFrozen,
            'config_file' => 'config/allocation.php',
        ];
    }

    /**
     * Freeze the CURRENT config file values into the examination DB.
     * Calling this again after a config-file change creates an auditable re-freeze.
     */
    public function finalize(?int $actorId): AllocationSetting
    {
        return DB::connection('exam')->transaction(function () use ($actorId): AllocationSetting {
            $current = $this->currentConfig();
            $setting = AllocationSetting::query()->whereKey(1)->lockForUpdate()->first();

            if (! $setting) {
                $setting = new AllocationSetting(['id' => 1, 'status' => 'draft']);
            }

            $fromStatus = (string) ($setting->status ?: 'draft');
            $oldHash = (string) ($setting->settings_hash ?? '');
            $newHash = $this->hashPayload($current);

            $setting->forceFill([
                'quota_priority' => $current['quota_priority'],
                'small_cadre_quota_threshold' => $current['quota_breakup_minimum_total_posts'],
                'mq_percent' => $current['mq_percent'],
                'cff_percent' => $current['cff_percent'],
                'em_percent' => $current['em_percent'],
                'phc_percent' => $current['phc_percent'],
                'status' => 'finalized',
                'settings_hash' => $newHash,
                'updated_by' => $actorId,
                'finalized_by' => $actorId,
                'finalized_at' => now(),
            ])->save();

            AllocationProcessingAudit::query()->create([
                'event' => $oldHash && ! hash_equals($oldHash, $newHash)
                    ? 'ALLOCATION_SETTINGS_REFROZEN'
                    : 'ALLOCATION_SETTINGS_FINALIZED',
                'actor_id' => $actorId,
                'from_status' => $fromStatus,
                'to_status' => 'finalized',
                'context' => [
                    'config_file' => 'config/allocation.php',
                    'old_settings_hash' => $oldHash ?: null,
                    'settings_hash' => $newHash,
                    'resolved_settings' => $current,
                ],
                'created_at' => now(),
            ]);

            if ($oldHash && ! hash_equals($oldHash, $newHash)) {
                $reason = 'A1 Allocation configuration changed and was re-frozen. Re-freeze A2 and re-run A3/A4.';
                $state = AllocationProcessingState::query()->whereKey(1)->lockForUpdate()->first();
                if ($state && (string) $state->status !== 'not_started') {
                    $state->forceFill([
                        'is_stale' => true,
                        'stale_reason' => $reason,
                    ])->save();
                }

                // A2 is an immutable snapshot of A1 + Seat Breakup + upstream result inputs.
                // Do not delete the historical snapshot/queues; explicitly retire it so it
                // can never be reused as the current Phase-1 authority.
                AllocationInputFreeze::query()->where('status', 'frozen')->update([
                    'status' => 'stale',
                    'updated_at' => now(),
                ]);

                // Result evidence remains immutable; only currentness metadata changes.
                AllocationRun::query()->where('status', 'phase1_complete')->where('is_stale', false)->update([
                    'is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now(),
                ]);
                AllocationA4Run::query()->where('status', 'a4_complete')->where('is_stale', false)->update([
                    'is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now(),
                ]);
                AllocationA5Run::query()->whereIn('status', ['validated_ok','validated_failed','finalized'])->where('is_stale', false)->update([
                    'is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $setting->refresh();
        });
    }

    public function storedFinalizedSummary(): array
    {
        $setting = $this->setting();
        if ((string) $setting->status !== 'finalized' || ! $setting->settings_hash) {
            throw new \RuntimeException('Allocation Settings are not finalized/frozen. Review config/allocation.php and freeze the current configuration.');
        }

        $currentHash = $this->currentConfigHash();
        if (! hash_equals((string) $setting->settings_hash, $currentHash)) {
            throw new \RuntimeException('ALLOCATION_SETTINGS_CONFIG_CHANGED: config/allocation.php differs from the frozen Allocation Settings snapshot. Review and re-freeze settings.');
        }

        return [
            'dataset_hash' => (string) $setting->settings_hash,
            'version' => 1,
        ];
    }

    public function verified(): AllocationSetting
    {
        $setting = $this->setting();
        if ((string) $setting->status !== 'finalized' || ! $setting->settings_hash) {
            throw ValidationException::withMessages([
                'allocation_settings' => 'Allocation Settings must be finalized/frozen before Allocation.',
            ]);
        }

        $snapshotHash = $this->hash($setting);
        if (! hash_equals((string) $setting->settings_hash, $snapshotHash)) {
            throw ValidationException::withMessages([
                'allocation_settings' => 'ALLOCATION_SETTINGS_HASH_MISMATCH: Frozen settings snapshot changed after finalization.',
            ]);
        }

        $currentHash = $this->currentConfigHash();
        if (! hash_equals((string) $setting->settings_hash, $currentHash)) {
            throw ValidationException::withMessages([
                'allocation_settings' => 'ALLOCATION_SETTINGS_CONFIG_CHANGED: config/allocation.php differs from the frozen snapshot. Review and re-freeze settings before Allocation.',
            ]);
        }

        return $setting;
    }

    private function validateResolvedConfig(array $resolved): void
    {
        $priority = array_values(array_unique((array) $resolved['quota_priority']));
        if (count($priority) !== 3 || array_values(array_intersect($priority, ['CFF', 'EM', 'PHC'])) !== $priority) {
            throw ValidationException::withMessages([
                'quota_priority' => 'config/allocation.php quota_priority must contain CFF, EM and PHC exactly once.',
            ]);
        }

        if ((int) $resolved['quota_breakup_minimum_total_posts'] !== 10) {
            throw ValidationException::withMessages([
                'quota_breakup_minimum_total_posts' => 'Locked Allocation rule requires Quota Breakup Minimum Total Posts = 10.',
            ]);
        }

        $total = (int) $resolved['mq_percent']
            + (int) $resolved['cff_percent']
            + (int) $resolved['em_percent']
            + (int) $resolved['phc_percent'];

        if ($total !== 100) {
            throw ValidationException::withMessages([
                'percentages' => 'config/allocation.php provisional breakup percentages must total 100.',
            ]);
        }
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }
}
