<?php

namespace App\Models;

use App\Enums\VivaProcessingStatus;

final class VivaProcessingState extends ExaminationModel
{
    public $incrementing = false;

    protected $fillable = [
        'id', 'status', 'latest_mapping_batch_id', 'latest_board_batch_id', 'latest_reconciliation_run_id', 'latest_processing_run_id',
        'reconciliation_generated_by', 'reconciliation_generated_at',
        'result_processed_by', 'result_processed_at', 'result_finalized_by',
        'result_finalized_at', 'summary', 'is_stale', 'stale_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => VivaProcessingStatus::class,
            'reconciliation_generated_at' => 'datetime', 'result_processed_at' => 'datetime',
            'result_finalized_at' => 'datetime', 'summary' => 'array', 'is_stale' => 'boolean',
        ];
    }
}
