<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationProcessingState;
use App\Models\ChoiceOptimizationSetting;
use Illuminate\Support\Facades\DB;

final class ChoiceOptimizationSettingsService
{
    public function setting(): ChoiceOptimizationSetting
    {
        return ChoiceOptimizationSetting::query()->firstOrCreate(
            ['id' => 1],
            ['optimization_enabled' => (bool) config('choice-optimization.default_enabled', false)]
        );
    }

    public function state(): ChoiceOptimizationProcessingState
    {
        return ChoiceOptimizationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
    }

    public function updateEnabled(bool $enabled, ?int $actorId): ChoiceOptimizationSetting
    {
        return DB::connection('exam')->transaction(function () use ($enabled, $actorId): ChoiceOptimizationSetting {
            $setting = ChoiceOptimizationSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $before = (bool) $setting->optimization_enabled;

            if ($before === $enabled) {
                return $setting;
            }

            $setting->forceFill(['optimization_enabled' => $enabled, 'updated_by' => $actorId])->save();

            $state = $this->state();
            $from = $state->status;
            $state->forceFill([
                'status' => $enabled ? 'not_started' : 'bypassed',
                'is_stale' => false,
                'stale_reason' => null,
                'dataset_hash' => null,
                'summary' => null,
                'finalized_by' => null,
                'finalized_at' => null,
            ])->save();

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => $enabled ? 'OPTIMIZATION_ENABLED' : 'OPTIMIZATION_DISABLED',
                'actor_id' => $actorId,
                'from_status' => $from,
                'to_status' => $state->status,
                'context' => [
                    'before' => $before,
                    'after' => $enabled,
                    'allocation_choice_source' => $enabled ? 'OPTIMIZED_CHOICE' : 'FINALIZED_VALIDATED_CHOICE',
                ],
                'created_at' => now(),
            ]);

            return $setting->refresh();
        });
    }
}
