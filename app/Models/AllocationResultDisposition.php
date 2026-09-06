<?php

namespace App\Models;

final class AllocationResultDisposition extends ExaminationModel
{
    protected $guarded = [];
    protected function casts(): array { return ['allocation_a5_run_id'=>'integer','registration_id'=>'integer','circular_entry_id'=>'integer','cadre_code'=>'integer','changed_at'=>'datetime']; }
}
