<?php

namespace App\Models;

/** Immutable audit row for one correction applied to an import staging record. */
final class ImportCorrectionEntry extends ExaminationModel
{
    protected $table = 'import_correction_entries';

    protected $guarded = [];

    /** Shared correction table stores created_at only; no updated_at column exists. */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'original_payload' => 'array',
            'corrected_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
