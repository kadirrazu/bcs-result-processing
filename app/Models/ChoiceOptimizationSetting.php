<?php

namespace App\Models;

final class ChoiceOptimizationSetting extends ExaminationModel
{
    public $incrementing = false;

    protected $fillable = ['id', 'optimization_enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['optimization_enabled' => 'boolean'];
    }
}
