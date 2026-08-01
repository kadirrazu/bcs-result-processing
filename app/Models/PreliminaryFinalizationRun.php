<?php

namespace App\Models;

final class PreliminaryFinalizationRun extends ExaminationModel
{
    protected $fillable = [
        'cutoff_decision_id', 'cutoff_mark', 'status', 'reason',
        'queued_by', 'queued_at', 'started_at', 'completed_at',
        'current_step', 'total_rows', 'processed_rows', 'progress_percent',
        'failure_message', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_mark' => 'decimal:2',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'progress_percent' => 'decimal:2',
            'summary' => 'array',
        ];
    }
}
