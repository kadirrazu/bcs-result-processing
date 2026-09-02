<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllocationResult extends ExaminationModel
{
    protected $fillable = [
        'allocation_run_id', 'input_candidate_id', 'registration_id', 'reg',
        'circular_entry_id', 'cadre_code', 'cadre_type', 'choice_position',
        'merit_position', 'merit_source', 'allocation_basis', 'movement_type',
        'decision_status', 'decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'choice_position' => 'integer', 'merit_position' => 'integer',
            'cadre_code' => 'integer',
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
