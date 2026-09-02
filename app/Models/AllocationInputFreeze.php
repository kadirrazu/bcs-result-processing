<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class AllocationInputFreeze extends ExaminationModel
{
    protected $fillable = [
        'version', 'status', 'choice_source', 'source_snapshot',
        'registration_hash', 'circular_hash', 'choice_hash', 'merit_hash',
        'settings_hash', 'seat_breakup_hash', 'input_fingerprint', 'queue_hash',
        'total_candidates', 'choice_ready_candidates', 'total_queue_entries',
        'skipped_choice_entries', 'frozen_by', 'frozen_at',
    ];

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'version' => 'integer',
            'total_candidates' => 'integer',
            'choice_ready_candidates' => 'integer',
            'total_queue_entries' => 'integer',
            'skipped_choice_entries' => 'integer',
            'frozen_at' => 'datetime',
        ];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(AllocationInputCandidate::class, 'input_freeze_id');
    }

    public function queueEntries(): HasMany
    {
        return $this->hasMany(AllocationInputQueueEntry::class, 'input_freeze_id');
    }
}
