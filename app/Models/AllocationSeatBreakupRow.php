<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllocationSeatBreakupRow extends ExaminationModel
{
    protected $fillable = [
        'seat_breakup_version_id', 'sl', 'cadre_code', 'total_post',
        'mq', 'cff', 'em', 'phc', 'circular_entry_id',
    ];

    public function circularEntry(): BelongsTo
    {
        return $this->belongsTo(CircularEntry::class, 'circular_entry_id');
    }

    protected function casts(): array
    {
        return [
            'cadre_code' => 'integer', 'total_post' => 'integer',
            'mq' => 'integer', 'cff' => 'integer', 'em' => 'integer', 'phc' => 'integer',
            'circular_entry_id' => 'integer',
        ];
    }
}
