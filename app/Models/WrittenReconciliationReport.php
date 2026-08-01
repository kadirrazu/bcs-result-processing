<?php

namespace App\Models;

final class WrittenReconciliationReport extends ExaminationModel
{
    protected $fillable = ['source_batch_id', 'summary', 'generated_by', 'generated_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'generated_at' => 'datetime'];
    }
}
