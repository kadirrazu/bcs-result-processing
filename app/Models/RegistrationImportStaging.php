<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Raw/normalized registration row waiting for validation and approval. */
final class RegistrationImportStaging extends ExaminationModel
{
    protected $table = 'registration_import_staging';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'has_quota' => 'boolean',
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RegistrationImportBatch::class, 'batch_id');
    }
}
