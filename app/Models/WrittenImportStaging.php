<?php

namespace App\Models;

use App\Enums\WrittenValidationStatus;

/** Raw and normalized candidate row retained before approval. */
final class WrittenImportStaging extends ExaminationModel
{
    protected $table = 'written_import_staging';

    protected $fillable = [
        'batch_id', 'source_row', 'raw_payload', 'registration_id', 'user_id', 'reg',
        'normalized_marks', 'prs_code', 'prs_mark', 'data_source_note', 'status',
        'validation_status', 'validation_errors', 'validation_warnings',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'normalized_marks' => 'array',
            'prs_mark' => 'decimal:2',
            'validation_status' => WrittenValidationStatus::class,
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
        ];
    }
}
