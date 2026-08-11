<?php

namespace App\Models;

use App\Enums\ChoiceSourceValidationStatus;

final class ChoiceValidationImportStaging extends ExaminationModel
{
    protected $table = 'choice_validation_import_staging';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array', 'raw_choices' => 'array', 'raw_choice_count' => 'integer',
            'validation_status' => ChoiceSourceValidationStatus::class,
            'validation_errors' => 'array', 'validation_warnings' => 'array',
        ];
    }
}
