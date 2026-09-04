<?php

namespace App\Models;

final class AllocationA6ExportAudit extends ExaminationModel
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['parameters' => 'array', 'cadre_code' => 'integer', 'generated_at' => 'datetime']; }
}
