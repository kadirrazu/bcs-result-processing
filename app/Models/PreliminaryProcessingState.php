<?php

namespace App\Models;

use App\Enums\PreliminaryProcessingStatus;

/** Singleton-like examination record powering the preliminary processing board. */
final class PreliminaryProcessingState extends ExaminationModel
{
    protected $fillable = [
        'status', 'latest_import_batch_id', 'cutoff_mark', 'cutoff_set_by',
        'cutoff_set_at', 'reconciliation_generated_by', 'reconciliation_generated_at',
        'result_finalized_by', 'result_finalized_at', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => PreliminaryProcessingStatus::class,
            'cutoff_mark' => 'decimal:2',
            'cutoff_set_at' => 'datetime',
            'reconciliation_generated_at' => 'datetime',
            'result_finalized_at' => 'datetime',
            'summary' => 'array',
        ];
    }
}
