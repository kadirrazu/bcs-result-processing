<?php

namespace App\Models;

/** Immutable database audit entry for a preliminary processing action. */
final class PreliminaryProcessingAudit extends ExaminationModel
{
    public $updated_at = null;

    protected $fillable = [
        'action', 'status_before', 'status_after', 'batch_id', 'processing_run_id',
        'registration_id', 'preliminary_result_id',
        'actor_id', 'actor_name', 'reason', 'summary', 'before_snapshot',
        'after_snapshot', 'ip_address', 'user_agent', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array', 'before_snapshot' => 'array', 'after_snapshot' => 'array',
            'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }
}
