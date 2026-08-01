<?php

namespace App\Models;

final class WrittenProcessingRun extends ExaminationModel
{
    protected $fillable = [
        'type', 'status', 'total_rows', 'processed_rows', 'progress_percent',
        'current_step', 'failure_message', 'created_by', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'float',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
