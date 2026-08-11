<?php

namespace App\Models;

final class ChoiceValidationProcessingAudit extends ExaminationModel
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['summary'=>'array','created_at'=>'datetime']; }
}
