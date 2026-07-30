<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Audit record for one registration spreadsheet import. */
final class RegistrationImportBatch extends ExaminationModel
{
    protected $fillable = [
        'original_name', 'stored_name', 'status', 'total_rows', 'inserted_rows',
        'updated_rows', 'failed_rows', 'warning_rows', 'identity_conflict_rows',
        'started_at', 'finished_at', 'rolled_back_at', 'rolled_back_by',
        'rollback_reason', 'error_file', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(RegistrationImportRow::class, 'batch_id');
    }
}
