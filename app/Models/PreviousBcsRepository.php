<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PreviousBcsRepository extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['bcs_number' => 'integer'];
    }

    public function datasets(): HasMany
    {
        return $this->hasMany(PreviousBcsRepositoryDataset::class, 'repository_id');
    }

    public function currentEffectiveDataset(): BelongsTo
    {
        return $this->belongsTo(PreviousBcsRepositoryDataset::class, 'current_effective_dataset_id');
    }
}
