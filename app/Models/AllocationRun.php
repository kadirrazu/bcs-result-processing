<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AllocationRun extends ExaminationModel
{
    protected $fillable = [
        'version', 'input_freeze_id', 'status', 'phase', 'input_fingerprint', 'queue_hash',
        'settings_hash', 'seat_breakup_hash', 'iteration_count', 'allocated_count',
        'unallocated_count', 'mq_count', 'cff_count', 'em_count', 'phc_count',
        'final_count', 'temporary_count', 'phase1_output_hash', 'seat_ledger_hash',
        'failure_message', 'is_stale', 'stale_reason', 'staled_at', 'started_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'iteration_count' => 'integer', 'allocated_count' => 'integer',
            'unallocated_count' => 'integer', 'mq_count' => 'integer', 'cff_count' => 'integer',
            'em_count' => 'integer', 'phc_count' => 'integer', 'final_count' => 'integer',
            'temporary_count' => 'integer', 'is_stale' => 'boolean', 'staled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function inputFreeze(): BelongsTo
    {
        return $this->belongsTo(AllocationInputFreeze::class, 'input_freeze_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AllocationResult::class, 'allocation_run_id');
    }

    public function seatLedgers(): HasMany
    {
        return $this->hasMany(AllocationSeatLedger::class, 'allocation_run_id');
    }
}
