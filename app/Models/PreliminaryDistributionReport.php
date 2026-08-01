<?php

namespace App\Models;

final class PreliminaryDistributionReport extends ExaminationModel
{
    protected $fillable = [
        'import_batch_id', 'reconciliation_report_id', 'eligible_candidates',
        'gg_candidates', 'tt_candidates', 'gt_candidates', 'distinct_marks',
        'minimum_mark', 'maximum_mark', 'distribution', 'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'minimum_mark' => 'decimal:2',
            'maximum_mark' => 'decimal:2',
            'distribution' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
