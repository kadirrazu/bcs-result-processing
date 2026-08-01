<?php

namespace App\Models;

/** Raw and normalized row retained until a preliminary import is approved or rolled back. */
final class PreliminaryImportStaging extends ExaminationModel
{
    protected $table = 'preliminary_import_staging';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
        ];
    }
}
