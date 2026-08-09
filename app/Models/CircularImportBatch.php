<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class CircularImportBatch extends ExaminationModel
{
    protected $fillable = [
        'original_filename', 'stored_path', 'status', 'total_rows', 'valid_rows', 'invalid_rows',
        'uploaded_by', 'approved_by', 'approved_at', 'approved_version', 'approval_note',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer', 'valid_rows' => 'integer', 'invalid_rows' => 'integer',
            'uploaded_by' => 'integer', 'approved_by' => 'integer', 'approved_at' => 'datetime',
            'approved_version' => 'integer',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CircularImportStaging::class, 'batch_id');
    }
}
