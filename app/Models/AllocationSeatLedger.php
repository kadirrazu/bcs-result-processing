<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllocationSeatLedger extends ExaminationModel
{
    protected $fillable = [
        'allocation_run_id', 'circular_entry_id', 'cadre_code', 'total_capacity',
        'mq_capacity', 'cff_capacity', 'em_capacity', 'phc_capacity',
        'mq_occupied', 'cff_occupied', 'em_occupied', 'phc_occupied',
        'mq_remaining', 'cff_remaining', 'em_remaining', 'phc_remaining',
    ];

    protected function casts(): array
    {
        return [
            'cadre_code' => 'integer', 'total_capacity' => 'integer', 'mq_capacity' => 'integer',
            'cff_capacity' => 'integer', 'em_capacity' => 'integer', 'phc_capacity' => 'integer',
            'mq_occupied' => 'integer', 'cff_occupied' => 'integer', 'em_occupied' => 'integer',
            'phc_occupied' => 'integer', 'mq_remaining' => 'integer', 'cff_remaining' => 'integer',
            'em_remaining' => 'integer', 'phc_remaining' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AllocationRun::class, 'allocation_run_id');
    }

    public function circularEntry(): BelongsTo
    {
        return $this->belongsTo(CircularEntry::class, 'circular_entry_id');
    }
}
