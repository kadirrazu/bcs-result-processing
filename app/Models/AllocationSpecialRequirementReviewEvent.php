<?php

namespace App\Models;

final class AllocationSpecialRequirementReviewEvent extends ExaminationModel
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['allocation_a5_run_id'=>'integer','allocation_a4_run_id'=>'integer','registration_id'=>'integer','circular_entry_id'=>'integer','actor_id'=>'integer','created_at'=>'datetime']; }
}
