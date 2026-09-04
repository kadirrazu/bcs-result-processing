<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AllocationA5Run extends ExaminationModel
{
    protected $fillable = [
        'version','allocation_a4_run_id','status','phase','a4_output_hash','circular_version','circular_hash',
        'registration_hash','candidate_result_hash','capacity_result_hash','total_allocated','candidate_passed',
        'candidate_failed','capacity_checked','capacity_failed','progress_percent','progress_current','progress_total',
        'progress_message','failure_message','is_stale','stale_reason','staled_at','started_by','started_at','completed_at',
        'finalized_by','finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'version'=>'integer','allocation_a4_run_id'=>'integer','circular_version'=>'integer','total_allocated'=>'integer',
            'candidate_passed'=>'integer','candidate_failed'=>'integer','capacity_checked'=>'integer','capacity_failed'=>'integer',
            'progress_percent'=>'integer','progress_current'=>'integer','progress_total'=>'integer','is_stale'=>'boolean',
            'staled_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime','finalized_at'=>'datetime',
        ];
    }

    public function a4Run(): BelongsTo { return $this->belongsTo(AllocationA4Run::class, 'allocation_a4_run_id'); }
    public function candidateResults(): HasMany { return $this->hasMany(AllocationA5CandidateResult::class, 'allocation_a5_run_id'); }
    public function capacityResults(): HasMany { return $this->hasMany(AllocationA5CapacityResult::class, 'allocation_a5_run_id'); }
}
