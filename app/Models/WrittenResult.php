<?php

namespace App\Models;

use App\Enums\WrittenCandidateStatus;
use App\Enums\WrittenQualifiedTrack;
use App\Enums\WrittenTrackResultStatus;
use App\Enums\WrittenValidationStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Approved written facts and derived track-level processing state. */
final class WrittenResult extends ExaminationModel
{
    protected $fillable = [
        'registration_id', 'user_id', 'reg', 'cadre_category', 'prs_code',
        'data_source_note', 'status', 'validation_status', 'general_result_status',
        'technical_result_status', 'written_qualified_track', 'general_actual_total',
        'general_counted_total', 'technical_actual_total', 'technical_counted_total',
        'general_fail_reasons', 'technical_fail_reasons', 'processing_flags',
        'comment', 'source_batch_id', 'finalized_at', 'last_edited_by',
        'last_edited_at', 'last_edit_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => WrittenCandidateStatus::class,
            'validation_status' => WrittenValidationStatus::class,
            'general_result_status' => WrittenTrackResultStatus::class,
            'technical_result_status' => WrittenTrackResultStatus::class,
            'written_qualified_track' => WrittenQualifiedTrack::class,
            'general_actual_total' => 'decimal:2',
            'general_counted_total' => 'decimal:2',
            'technical_actual_total' => 'decimal:2',
            'technical_counted_total' => 'decimal:2',
            'general_fail_reasons' => 'array',
            'technical_fail_reasons' => 'array',
            'processing_flags' => 'array',
            'finalized_at' => 'datetime',
            'last_edited_at' => 'datetime',
        ];
    }

    public function marks(): HasMany
    {
        return $this->hasMany(WrittenCandidateMark::class, 'written_result_id');
    }
}
