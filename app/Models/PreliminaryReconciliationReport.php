<?php

namespace App\Models;

final class PreliminaryReconciliationReport extends ExaminationModel
{
    protected $fillable = [
        'import_batch_id', 'active_registered', 'imported_rows', 'present_with_mark',
        'present_with_status_text', 'cancelled_with_reason', 'cancelled_without_reason',
        'absent', 'excluded_non_active_registration', 'summary', 'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
