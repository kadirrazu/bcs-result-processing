<?php

namespace App\Models;

final class ChoiceOptimizationGoogleFormRecommendation extends ExaminationModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_bcs_number' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }
}
