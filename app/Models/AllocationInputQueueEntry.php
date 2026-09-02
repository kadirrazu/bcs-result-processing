<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllocationInputQueueEntry extends ExaminationModel
{
    public $timestamps = false;

    protected $fillable = [
        'input_freeze_id', 'registration_id', 'circular_entry_id', 'cadre_code', 'cadre_type',
        'choice_position', 'merit_position', 'merit_source', 'general_merit_position',
        'technical_merit_position', 'eligible_cff', 'eligible_em', 'eligible_phc',
        'total_post', 'mq', 'cff', 'em', 'phc', 'queue_key', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cadre_code' => 'integer', 'choice_position' => 'integer', 'merit_position' => 'integer',
            'general_merit_position' => 'integer', 'technical_merit_position' => 'integer',
            'eligible_cff' => 'boolean', 'eligible_em' => 'boolean', 'eligible_phc' => 'boolean',
            'total_post' => 'integer', 'mq' => 'integer', 'cff' => 'integer', 'em' => 'integer', 'phc' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function freeze(): BelongsTo
    {
        return $this->belongsTo(AllocationInputFreeze::class, 'input_freeze_id');
    }

    public function circularEntry(): BelongsTo
    {
        return $this->belongsTo(CircularEntry::class, 'circular_entry_id');
    }
}
