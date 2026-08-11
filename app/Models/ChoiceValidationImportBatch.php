<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChoiceValidationImportBatch extends ExaminationModel
{
    protected $fillable = [
        'examination_id', 'original_name', 'stored_name', 'status', 'configured_maximum_choices',
        'total_rows', 'processed_rows', 'valid_rows', 'invalid_rows', 'approved_rows', 'source_version',
        'progress_percent', 'failure_message', 'created_by', 'approved_by', 'queued_at',
        'started_at', 'validated_at', 'approved_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'configured_maximum_choices' => 'integer',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'valid_rows' => 'integer',
            'invalid_rows' => 'integer',
            'approved_rows' => 'integer',
            'source_version' => 'integer',
            'progress_percent' => 'float',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function stagingRows(): HasMany
    {
        return $this->hasMany(ChoiceValidationImportStaging::class, 'batch_id');
    }
}
