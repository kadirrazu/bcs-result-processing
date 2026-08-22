<?php

namespace App\Models;

final class ChoiceOptimizationEffectiveChoice extends ExaminationModel
{
    protected $table = 'choice_optimization_effective_choices';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'validated_choice_codes' => 'array',
            'omr_override_choice_codes' => 'array',
            'effective_choice_codes' => 'array',
            'approved_at' => 'datetime',
        ];
    }
}
