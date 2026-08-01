<?php

namespace App\Models;

/** Subject-level source truth and counted processing value. */
final class WrittenCandidateMark extends ExaminationModel
{
    protected $fillable = [
        'written_result_id', 'registration_id', 'subject_code', 'raw_value',
        'actual_mark', 'counted_mark', 'attendance_status', 'paper_crashed',
        'crash_threshold', 'is_applicable', 'has_warning', 'warning_codes',
    ];

    protected function casts(): array
    {
        return [
            'actual_mark' => 'decimal:2',
            'counted_mark' => 'decimal:2',
            'crash_threshold' => 'decimal:2',
            'paper_crashed' => 'boolean',
            'is_applicable' => 'boolean',
            'has_warning' => 'boolean',
            'warning_codes' => 'array',
        ];
    }
}
