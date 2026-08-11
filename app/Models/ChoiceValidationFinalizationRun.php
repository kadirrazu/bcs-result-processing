<?php

namespace App\Models;

final class ChoiceValidationFinalizationRun extends ExaminationModel
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_version' => 'integer',
            'validation_version' => 'integer',
            'circular_version' => 'integer',
            'validation_run_id' => 'integer',
            'total_candidates' => 'integer',
            'valid_candidates' => 'integer',
            'not_applicable_candidates' => 'integer',
            'zero_valid_choice_candidates' => 'integer',
            'kept_choices' => 'integer',
            'removed_choices' => 'integer',
            'expanded_choices' => 'integer',
            'finalized_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
