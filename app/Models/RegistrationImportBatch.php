<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Audit record for one registration staging/validation/approval workflow. */
final class RegistrationImportBatch extends ExaminationModel
{
    protected $fillable = [
        'examination_id', 'original_name', 'stored_name', 'status', 'total_rows', 'processed_rows',
        'staged_rows', 'current_row', 'chunk_size', 'current_chunk', 'total_chunks', 'progress_percent',
        'inserted_rows', 'updated_rows', 'failed_rows', 'warning_rows', 'valid_rows', 'invalid_rows',
        'approved_rows', 'identity_conflict_rows', 'started_at', 'finished_at', 'validated_at',
        'approved_at', 'approved_by', 'rolled_back_at', 'rolled_back_by', 'rollback_reason',
        'error_file', 'failure_message', 'queued_at', 'heartbeat_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'decimal:4',
            'started_at' => 'datetime',
            'queued_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'finished_at' => 'datetime',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(RegistrationImportRow::class, 'batch_id');
    }

    public function stagingRows(): HasMany
    {
        return $this->hasMany(RegistrationImportStaging::class, 'batch_id');
    }
}
