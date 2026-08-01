<?php

namespace App\Models;

use App\Enums\WrittenProcessingStatus;

/** Singleton state board for the selected examination's Written module. */
final class WrittenProcessingState extends ExaminationModel
{
    public $incrementing = false;

    protected $fillable = [
        'id', 'status', 'latest_import_batch_id', 'reconciliation_generated_at',
        'reconciliation_generated_by', 'latest_reconciliation_report_id',
        'latest_processing_run_id', 'paper_crash_processed_at', 'paper_crash_processed_by',
        'result_finalized_at', 'result_finalized_by', 'summary', 'is_stale', 'stale_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => WrittenProcessingStatus::class,
            'reconciliation_generated_at' => 'datetime',
            'paper_crash_processed_at' => 'datetime',
            'result_finalized_at' => 'datetime',
            'summary' => 'array',
            'is_stale' => 'boolean',
        ];
    }
}
