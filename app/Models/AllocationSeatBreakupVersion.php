<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class AllocationSeatBreakupVersion extends ExaminationModel
{
    protected $fillable = [
        'version', 'status', 'circular_version', 'circular_hash', 'dataset_hash',
        'total_rows', 'total_posts', 'mq_posts', 'cff_posts', 'em_posts', 'phc_posts',
        'source_file', 'validation_summary', 'created_by', 'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'circular_version' => 'integer',
            'total_rows' => 'integer', 'total_posts' => 'integer',
            'mq_posts' => 'integer', 'cff_posts' => 'integer', 'em_posts' => 'integer', 'phc_posts' => 'integer',
            'validation_summary' => 'array', 'finalized_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(AllocationSeatBreakupRow::class, 'seat_breakup_version_id');
    }
}
