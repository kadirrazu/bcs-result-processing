<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChoiceOptimizationHistoricalChoice extends ExaminationModel
{
    protected $guarded = [];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    protected function casts(): array
    {
        return [
            'input_choice_codes' => 'array',
            'historical_recommendations' => 'array',
            'matched_cutoff' => 'array',
            'removed_choice_codes' => 'array',
            'final_choice_codes' => 'array',
            'warnings' => 'array',
            'blocking_issues' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
