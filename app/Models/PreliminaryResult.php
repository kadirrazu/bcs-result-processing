<?php

namespace App\Models;

use App\Enums\PreliminaryCandidateStatus;
use App\Enums\PreliminaryResultStatus;
use App\Enums\PreliminaryValidationStatus;

/** Approved preliminary facts for one registered candidate. */
final class PreliminaryResult extends ExaminationModel
{
    protected $fillable = [
        'registration_id', 'user_id', 'reg', 'mark', 'raw_candidate_status',
        'candidate_status', 'result_status', 'applied_cutoff_mark',
        'validation_status', 'source_batch_id', 'finalized_at',
        'last_edited_by', 'last_edited_at', 'last_edit_reason',
    ];

    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
            'applied_cutoff_mark' => 'decimal:2',
            'candidate_status' => PreliminaryCandidateStatus::class,
            'result_status' => PreliminaryResultStatus::class,
            'validation_status' => PreliminaryValidationStatus::class,
            'finalized_at' => 'datetime',
            'last_edited_at' => 'datetime',
        ];
    }
}
