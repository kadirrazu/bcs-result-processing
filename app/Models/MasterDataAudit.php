<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Central immutable audit record for manual master-data changes. */
final class MasterDataAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'module', 'entity_type', 'entity_id', 'action', 'actor_id', 'actor_name',
        'reason', 'changed_fields', 'before_snapshot', 'after_snapshot',
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
