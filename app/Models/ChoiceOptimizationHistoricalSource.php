<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChoiceOptimizationHistoricalSource extends ExaminationModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_bcs_number' => 'integer',
            'repository_dataset_id' => 'integer',
            'repository_dataset_version' => 'integer',
            'candidate_count' => 'integer',
            'matched_count' => 'integer',
            'review_count' => 'integer',
            'no_match_count' => 'integer',
            'last_pulled_at' => 'datetime',
            'included_in_optimization' => 'boolean',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ChoiceOptimizationHistoricalMatch::class, 'historical_source_id');
    }
}
