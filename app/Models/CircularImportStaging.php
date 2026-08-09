<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CircularImportStaging extends ExaminationModel
{
    protected $table = 'circular_import_staging';

    protected $fillable = [
        'batch_id', 'row_number', 'raw_data', 'normalized_data', 'validation_status', 'validation_errors',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer', 'raw_data' => 'array', 'normalized_data' => 'array',
            'validation_errors' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CircularImportBatch::class, 'batch_id');
    }
}
