<?php

namespace App\Models;

final class AllocationDecisionEvent extends ExaminationModel
{
    public $timestamps = false;

    protected $fillable = [
        'allocation_run_id', 'sequence_no', 'iteration_no', 'phase', 'event',
        'registration_id', 'circular_entry_id', 'cadre_code', 'allocation_basis',
        'reason', 'context', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer', 'iteration_no' => 'integer', 'cadre_code' => 'integer',
            'context' => 'array', 'created_at' => 'datetime',
        ];
    }
}
