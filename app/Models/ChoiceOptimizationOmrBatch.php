<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChoiceOptimizationOmrBatch extends ExaminationModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'configured_maximum_choices' => 'integer',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'conflict_rows' => 'integer',
            'review_rows' => 'integer',
            'approved_rows' => 'integer',
            'progress_percent' => 'float',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function stagingRows(): HasMany
    {
        return $this->hasMany(ChoiceOptimizationOmrStaging::class, 'batch_id');
    }
}
