<?php

namespace App\Models;

final class AllocationProcessingState extends ExaminationModel
{
    public $incrementing = false;

    protected $fillable = [
        'id', 'status', 'finalized_seat_breakup_version_id', 'is_stale', 'stale_reason',
        'source_snapshot', 'input_fingerprint', 'output_hash', 'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'is_stale' => 'boolean', 'source_snapshot' => 'array', 'finalized_at' => 'datetime',
        ];
    }
}
