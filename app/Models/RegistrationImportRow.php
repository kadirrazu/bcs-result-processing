<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Row-level import outcome and reversible before/after audit snapshot. */
final class RegistrationImportRow extends ExaminationModel
{
    protected $fillable = [
        'batch_id', 'source_row', 'registration_id', 'reg', 'user_id', 'action',
        'warnings', 'errors', 'before_data', 'after_data',
    ];

    protected function casts(): array
    {
        return [
            'warnings' => 'array',
            'errors' => 'array',
            'before_data' => 'array',
            'after_data' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RegistrationImportBatch::class, 'batch_id');
    }
}
