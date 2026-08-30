<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationGoogleFormRecommendation;
use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationSetting;
use Illuminate\Support\Facades\DB;

final class ChoiceOptimizationGoogleFormDecisionService
{
    public function __construct(
        private readonly ChoiceOptimizationHistoricalStalenessService $staleness,
    ) {}

    public function update(bool $enabled, ?int $actorId): ChoiceOptimizationSetting
    {
        return DB::connection('exam')->transaction(function () use ($enabled, $actorId): ChoiceOptimizationSetting {
            $setting = ChoiceOptimizationSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $before = $setting->google_form_enabled;

            if ($before !== null && (bool) $before === $enabled) {
                return $setting;
            }

            if (! $enabled && ChoiceOptimizationGoogleFormRecommendation::query()->exists()) {
                abort(409, 'Google Form cannot be changed to NO after accepted recommendations exist. Remove/revise the accepted dataset through an explicit correction workflow first.');
            }

            $setting->forceFill([
                'google_form_enabled' => $enabled,
                'google_form_decided_by' => $actorId,
                'google_form_decided_at' => now(),
                'updated_by' => $actorId,
            ])->save();

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => $enabled ? 'GOOGLE_FORM_ENABLED' : 'GOOGLE_FORM_BYPASSED',
                'actor_id' => $actorId,
                'context' => [
                    'before' => $before,
                    'after' => $enabled,
                    'meaning' => $enabled
                        ? 'Google Form historical recommendation workflow enabled.'
                        : 'Google Form intentionally bypassed; it must not gate downstream optimization.',
                ],
                'created_at' => now(),
            ]);

            $this->staleness->markIfProduced(
                'Google Form historical recommendation decision changed. Historical Choice Optimization must be re-processed.',
                $actorId,
                ['google_form_enabled' => $enabled]
            );

            return $setting->refresh();
        });
    }
}
