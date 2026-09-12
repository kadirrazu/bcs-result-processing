<?php

namespace App\Models;

final class ManualAllocationChoiceAdjustmentEvent extends ExaminationModel
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['registration_id'=>'integer','choice_code'=>'integer','actor_id'=>'integer','created_at'=>'datetime']; }
}
