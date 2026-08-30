<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChoiceOptimizationGoogleFormBatch extends ExaminationModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'merged_rows' => 'integer',
            'progress_percent' => 'float',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'validated_at' => 'datetime',
            'merged_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ChoiceOptimizationGoogleFormRow::class, 'batch_id');
    }
}
