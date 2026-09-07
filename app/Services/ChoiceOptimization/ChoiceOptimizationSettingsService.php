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
        $setting = ChoiceOptimizationSetting::query()->firstOrCreate(
            ['id' => 1],
            ['optimization_enabled' => true]
        );
        if (! (bool) $setting->optimization_enabled) {
            $setting->forceFill(['optimization_enabled' => true])->save();
        }
        return $setting->refresh();
    }

    public function state(): ChoiceOptimizationProcessingState
    {
        return ChoiceOptimizationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
    }

    public function updateEnabled(bool $enabled, ?int $actorId): ChoiceOptimizationSetting
    {
        // Choice Optimization is now mandatory because the final Written-track Filter
        // is the authoritative Allocation-ready Choice projection. The legacy global
        // YES/NO bypass is intentionally retired.
        return DB::connection('exam')->transaction(function () use ($actorId): ChoiceOptimizationSetting {
            $setting = ChoiceOptimizationSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            if (! (bool) $setting->optimization_enabled) {
                $setting->forceFill(['optimization_enabled' => true, 'updated_by' => $actorId])->save();
            }
            return $setting->refresh();
        });
    }
}
