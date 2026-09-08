<?php

namespace App\Models;

use App\Enums\ExaminationStatus;
use App\Enums\ExaminationType;
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
        'bcs_type',
        'advertisement_date',
        'age_calculation_date',
        'status',
        'is_enabled',
        'is_completed',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bcs_number' => 'integer',
            'status' => ExaminationStatus::class,
            'bcs_type' => ExaminationType::class,
            'advertisement_date' => 'date',
            'age_calculation_date' => 'date',
            'is_enabled' => 'boolean',
            'is_completed' => 'boolean',
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
