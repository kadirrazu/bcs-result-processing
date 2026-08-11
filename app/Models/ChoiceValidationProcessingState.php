<?php
namespace App\Models;
final class ChoiceValidationProcessingState extends ExaminationModel
{
    protected $guarded = [];
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'current_source_version' => 'integer',
            'approved_source_version' => 'integer',
            'current_validation_version' => 'integer',
            'latest_validation_run_id' => 'integer',
            'validation_completed_at' => 'datetime',
            'finalized_validation_version' => 'integer',
            'latest_finalization_run_id' => 'integer',
            'finalized_at' => 'datetime',
            'is_stale' => 'boolean',
            'summary' => 'array',
        ];
    }
}
