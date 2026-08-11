<?php

namespace App\Models;

final class ChoiceValidationManualCorrection extends ExaminationModel
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'corrected_snapshot' => 'array',
            'changed_positions' => 'array',
            'revalidated_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
