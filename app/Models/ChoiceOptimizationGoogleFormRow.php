<?php

namespace App\Models;

final class ChoiceOptimizationGoogleFormRow extends ExaminationModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'previous_bcs_number' => 'integer',
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
            'merged_at' => 'datetime',
        ];
    }
}
