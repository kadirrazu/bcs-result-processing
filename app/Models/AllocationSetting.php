<?php

namespace App\Models;

final class AllocationSetting extends ExaminationModel
{
    public $incrementing = false;

    protected $fillable = [
        'id', 'quota_priority', 'small_cadre_quota_threshold',
        'mq_percent', 'cff_percent', 'em_percent', 'phc_percent',
        'status', 'settings_hash', 'updated_by', 'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'quota_priority' => 'array',
            'small_cadre_quota_threshold' => 'integer',
            'mq_percent' => 'integer', 'cff_percent' => 'integer',
            'em_percent' => 'integer', 'phc_percent' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }
}
