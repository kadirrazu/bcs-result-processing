<?php

namespace App\Models;

use App\Enums\ExaminationStatus;
use Database\Factories\ExaminationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Central registry entry for one physically isolated BCS examination database.
 */
class Examination extends Model
{
    /** @use HasFactory<ExaminationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'bcs_number',
        'name',
        'slug',
        'database_name',
        'status',
        'is_enabled',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bcs_number' => 'integer',
            'status' => ExaminationStatus::class,
            'is_enabled' => 'boolean',
            'database_checked_at' => 'datetime',
            'database_migration_batch' => 'integer',
        ];
    }

    public function isSelectable(): bool
    {
        return $this->is_enabled && $this->status !== ExaminationStatus::Archived;
    }

    public function databaseIsConnected(): bool
    {
        return $this->database_health_status === 'connected';
    }
}
