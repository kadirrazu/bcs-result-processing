<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PreviousBcsRepositoryRow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'b_date' => 'date',
            'dob' => 'date',
            'ssc_year' => 'integer',
            'hsc_year' => 'integer',
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(PreviousBcsRepositoryDataset::class, 'dataset_id');
    }
}
