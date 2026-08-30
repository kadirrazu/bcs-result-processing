<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChoiceOptimizationConsolidatedHistoricalRecommendation extends ExaminationModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_bcs_number' => 'integer',
            'sources' => 'array',
            'conflict_cadres' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }
}
