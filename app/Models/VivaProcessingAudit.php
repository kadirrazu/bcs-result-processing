<?php

namespace App\Models;

final class VivaProcessingAudit extends ExaminationModel
{
    public $updated_at = null;

    protected $fillable = [
        'action', 'status_before', 'status_after', 'batch_id', 'registration_id', 'viva_result_id',
        'actor_id', 'actor_name', 'reason', 'changed_fields', 'summary', 'before_snapshot',
        'after_snapshot', 'ip_address', 'user_agent', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array', 'summary' => 'array', 'before_snapshot' => 'array', 'after_snapshot' => 'array',
            'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }
}
