<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllocationInputCandidate extends ExaminationModel
{
    protected $fillable = [
        'input_freeze_id', 'registration_id', 'merit_result_id', 'user_id', 'reg',
        'cadre_category', 'general_merit_position', 'quota_entitlement', 'choice_codes',
        'choice_source', 'skip_reason',
    ];

    protected function casts(): array
    {
        return [
            'general_merit_position' => 'integer',
            'quota_entitlement' => 'array',
            'choice_codes' => 'array',
        ];
    }

    public function freeze(): BelongsTo
    {
        return $this->belongsTo(AllocationInputFreeze::class, 'input_freeze_id');
    }
}
