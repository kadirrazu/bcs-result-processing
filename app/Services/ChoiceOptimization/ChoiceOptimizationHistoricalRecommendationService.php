<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationHistoricalMatch;
use Illuminate\Database\Eloquent\Collection;

final class ChoiceOptimizationHistoricalRecommendationService
{
    /**
     * Current authoritative workspace snapshot of confirmed historical recommendations.
     * Auto-matched and operator-confirmed rows both use match_status=matched.
     */
    public function confirmedForRegistration(int $registrationId): Collection
    {
        return ChoiceOptimizationHistoricalMatch::query()
            ->with('source')
            ->where('registration_id', $registrationId)
            ->where('match_status', 'matched')
            ->orderBy('previous_bcs_number')
            ->get();
    }

    public function confirmedForSource(int $historicalSourceId): Collection
    {
        return ChoiceOptimizationHistoricalMatch::query()
            ->where('historical_source_id', $historicalSourceId)
            ->where('match_status', 'matched')
            ->orderBy('current_reg')
            ->get();
    }
}
