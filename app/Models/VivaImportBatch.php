<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class VivaImportBatch extends ExaminationModel
{
    protected $fillable = [
        'examination_id', 'import_type', 'original_name', 'stored_name', 'status',
        'total_rows', 'processed_rows', 'staged_rows', 'valid_rows', 'warning_rows',
        'invalid_rows', 'identity_conflict_rows', 'approved_rows', 'inserted_rows',
        'updated_rows', 'progress_percent', 'failure_message', 'created_by',
        'approved_by', 'queued_at', 'started_at', 'finished_at', 'validated_at',
        'approved_at', 'rolled_back_at', 'rolled_back_by', 'rollback_reason',
    ];

    public function mappingRows(): HasMany
    {
        return $this->hasMany(VivaMappingImportStaging::class, 'batch_id');
    }

    public function boardRows(): HasMany
    {
        return $this->hasMany(VivaBoardImportStaging::class, 'batch_id');
    }

    protected function casts(): array
    {
        return [
            'progress_percent' => 'decimal:4',
            'queued_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime',
            'validated_at' => 'datetime', 'approved_at' => 'datetime', 'rolled_back_at' => 'datetime',
        ];
    }
}
