<?php

namespace App\Models;

/** Immutable database audit record for Written processing and corrections. */
final class WrittenProcessingAudit extends ExaminationModel
{
    public $timestamps = false;

    protected $fillable = [
        'action', 'status_before', 'status_after', 'batch_id', 'processing_run_id',
        'registration_id', 'written_result_id', 'actor_id', 'actor_name', 'reason',
        'changed_fields', 'summary', 'before_snapshot', 'after_snapshot',
        'ip_address', 'user_agent', 'started_at', 'completed_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'summary' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
