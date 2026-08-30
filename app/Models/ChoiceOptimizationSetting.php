<?php

namespace App\Models;

final class ChoiceOptimizationSetting extends ExaminationModel
{
    public $incrementing = false;

    protected $fillable = ['id', 'optimization_enabled', 'google_form_enabled', 'google_form_decided_by', 'google_form_decided_at', 'updated_by'];

    protected function casts(): array
    {
        return [
            'optimization_enabled' => 'boolean',
            'google_form_enabled' => 'boolean',
            'google_form_decided_at' => 'datetime',
        ];
    }
}
