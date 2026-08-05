<?php

namespace App\Models;

final class VivaProcessingRun extends ExaminationModel
{
    protected $fillable = [
        'processing_version', 'status', 'total_rows', 'processed_rows',
        'academic_processed_count', 'pass_count', 'fail_count', 'absent_count',
        'cancelled_count', 'withheld_count', 'expelled_count', 'progress_percent',
        'full_mark', 'pass_percent', 'pass_mark', 'current_step', 'summary',
        'failure_message', 'created_by', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'float',
            'full_mark' => 'decimal:2',
            'pass_percent' => 'decimal:2',
            'pass_mark' => 'decimal:2',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
