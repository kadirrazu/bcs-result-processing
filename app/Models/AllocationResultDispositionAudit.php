<?php

namespace App\Models;

final class AllocationResultDispositionAudit extends ExaminationModel
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['allocation_a5_run_id'=>'integer','registration_id'=>'integer','cadre_code'=>'integer','created_at'=>'datetime']; }
}
