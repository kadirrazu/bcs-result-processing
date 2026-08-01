<?php

namespace App\Models;

final class PreliminaryCutoffDecision extends ExaminationModel
{
    protected $fillable = [
        'distribution_report_id', 'cutoff_mark', 'status', 'reason',
        'proposed_by', 'proposed_at', 'approved_by', 'approved_at',
        'approval_reason', 'pass_total', 'pass_gg', 'pass_tt', 'pass_gt',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_mark' => 'decimal:2',
            'proposed_at' => 'datetime',
            'approved_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }
}
