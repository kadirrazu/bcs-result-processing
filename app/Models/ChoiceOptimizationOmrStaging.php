<?php

namespace App\Models;

final class ChoiceOptimizationOmrStaging extends ExaminationModel
{
    protected $table = 'choice_optimization_omr_staging';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'raw_choices' => 'array',
            'raw_choice_count' => 'integer',
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
            'validated_omr_choice_codes' => 'array',
            'choice_validation_details' => 'array',
            'resolved_at' => 'datetime',
            'decision_resolved_at' => 'datetime',
        ];
    }
}
