<?php

namespace App\Models;

use App\Enums\VivaCandidateStatus;
use App\Enums\VivaResultStatus;
use App\Enums\VivaValidationStatus;

final class VivaResult extends ExaminationModel
{
    protected $fillable = [
        'viva_candidate_mapping_id', 'registration_id', 'written_result_id', 'user_id', 'reg', 'code',
        'cadre_category', 'written_qualified_track', 'raw_viva_date', 'viva_date', 'member_id', 'raw_mark',
        'mark', 'attendance_status', 'raw_viva_cff', 'raw_viva_em', 'raw_viva_phc', 'viva_cff', 'viva_em',
        'viva_phc', 'raw_invalid_flag', 'raw_issue_flag', 'invalid_flag', 'issue_flag', 'quota_mismatch',
        'quota_mismatch_details', 'high_mark_review', 'status', 'validation_status', 'viva_result_status',
        'comment', 'source_batch_id', 'viva_fail_reasons', 'processing_snapshot', 'processing_version',
        'processing_run_id', 'processed_by', 'processed_at', 'finalized_at', 'last_edited_by', 'last_edited_at', 'last_edit_reason',
    ];

    protected function casts(): array
    {
        return [
            'viva_date' => 'date', 'mark' => 'decimal:2', 'viva_cff' => 'boolean', 'viva_em' => 'boolean',
            'viva_phc' => 'boolean', 'invalid_flag' => 'boolean', 'issue_flag' => 'boolean',
            'quota_mismatch' => 'boolean', 'quota_mismatch_details' => 'array', 'high_mark_review' => 'boolean',
            'status' => VivaCandidateStatus::class, 'validation_status' => VivaValidationStatus::class,
            'viva_result_status' => VivaResultStatus::class, 'viva_fail_reasons' => 'array', 'processing_snapshot' => 'array',
            'processed_at' => 'datetime', 'finalized_at' => 'datetime', 'last_edited_at' => 'datetime',
        ];
    }
}
