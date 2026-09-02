<?php

namespace App\Models;

final class AllocationProcessingState extends ExaminationModel
{
    public $incrementing = false;

    protected $fillable = [
        'id', 'status', 'finalized_seat_breakup_version_id', 'is_stale', 'stale_reason',
        'source_snapshot', 'input_fingerprint', 'output_hash', 'finalized_by', 'finalized_at',
        'phase', 'progress_percent', 'progress_current', 'progress_total', 'progress_message', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_stale' => 'boolean', 'source_snapshot' => 'array', 'finalized_at' => 'datetime',
            'progress_percent' => 'integer', 'progress_current' => 'integer', 'progress_total' => 'integer',
        ];
    }
}
