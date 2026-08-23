<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChoiceOptimizationHistoricalMatch extends ExaminationModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_bcs_number' => 'integer',
            'repository_dataset_id' => 'integer',
            'repository_row_id' => 'integer',
            'match_evidence' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ChoiceOptimizationHistoricalSource::class, 'historical_source_id');
    }
}
