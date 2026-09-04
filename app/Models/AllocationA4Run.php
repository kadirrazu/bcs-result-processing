<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AllocationA4Run extends ExaminationModel
{
    protected $fillable = [
        'version', 'phase1_run_id', 'input_freeze_id', 'status', 'phase', 'input_fingerprint',
        'queue_hash', 'phase1_output_hash', 'phase1_seat_ledger_hash', 'iteration_count',
        'progress_percent', 'progress_current', 'progress_total', 'progress_message',
        'allocated_count', 'unallocated_count', 'mq_count', 'cff_count', 'em_count', 'phc_count',
        'nm_count', 'shifted_count', 'quota_to_merit_count', 'a4_output_hash', 'seat_ledger_hash',
        'movement_hash', 'failure_message', 'is_stale', 'stale_reason', 'staled_at', 'started_by', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'phase1_run_id' => 'integer', 'input_freeze_id' => 'integer',
            'iteration_count' => 'integer', 'progress_percent' => 'integer',
            'progress_current' => 'integer', 'progress_total' => 'integer',
            'allocated_count' => 'integer', 'unallocated_count' => 'integer',
            'mq_count' => 'integer', 'cff_count' => 'integer', 'em_count' => 'integer',
            'phc_count' => 'integer', 'nm_count' => 'integer', 'shifted_count' => 'integer',
            'quota_to_merit_count' => 'integer', 'is_stale' => 'boolean', 'staled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function phase1Run(): BelongsTo { return $this->belongsTo(AllocationRun::class, 'phase1_run_id'); }
    public function inputFreeze(): BelongsTo { return $this->belongsTo(AllocationInputFreeze::class, 'input_freeze_id'); }
    public function results(): HasMany { return $this->hasMany(AllocationA4Result::class, 'allocation_a4_run_id'); }
    public function seatLedgers(): HasMany { return $this->hasMany(AllocationA4SeatLedger::class, 'allocation_a4_run_id'); }
    public function movements(): HasMany { return $this->hasMany(AllocationA4MovementEvent::class, 'allocation_a4_run_id'); }
}
