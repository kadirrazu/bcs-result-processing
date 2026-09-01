<?php

namespace App\Models;

final class AllocationProcessingAudit extends ExaminationModel
{
    public $timestamps = false;

    protected $fillable = ['event', 'actor_id', 'from_status', 'to_status', 'context', 'created_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }
}
