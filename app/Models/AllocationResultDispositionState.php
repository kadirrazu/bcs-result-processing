<?php

namespace App\Models;

final class AllocationResultDispositionState extends ExaminationModel
{
    protected $guarded = [];
    protected function casts(): array { return ['allocation_a5_run_id'=>'integer','revision'=>'integer','active_count'=>'integer','withheld_count'=>'integer','cancelled_count'=>'integer','changed_at'=>'datetime']; }
}
