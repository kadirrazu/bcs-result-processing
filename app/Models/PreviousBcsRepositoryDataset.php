<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PreviousBcsRepositoryDataset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'staged_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'progress_percent' => 'float',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'staged_at' => 'datetime',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(PreviousBcsRepository::class, 'repository_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PreviousBcsRepositoryRow::class, 'dataset_id');
    }
}
