<?php

namespace App\Models;

final class VivaReconciliationRun extends ExaminationModel
{
    protected $fillable = [
        'status', 'total_candidates', 'processed_candidates', 'progress_percent',
        'eligible_count', 'mapped_count', 'board_data_count', 'missing_mapping_count', 'missing_board_count',
        'appeared_count', 'absent_count', 'active_count', 'cancelled_count', 'withheld_count', 'expelled_count',
        'warning_count', 'quota_mismatch_count', 'quota_cff_mismatch_count', 'quota_em_mismatch_count',
        'quota_phc_mismatch_count', 'source_invalid_count', 'source_issue_count', 'high_mark_count',
        'track_summary', 'category_summary', 'review_summary', 'created_by', 'started_at', 'finished_at', 'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'decimal:4',
            'track_summary' => 'array',
            'category_summary' => 'array',
            'review_summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
