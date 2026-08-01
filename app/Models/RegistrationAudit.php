<?php

namespace App\Models;

/** Immutable examination-database audit entry for a manual registration correction. */
final class RegistrationAudit extends ExaminationModel
{
    public $timestamps = false;

    protected $fillable = [
        'registration_id', 'action', 'actor_id', 'actor_name', 'reason',
        'changed_fields', 'before_snapshot', 'after_snapshot',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_fields' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
