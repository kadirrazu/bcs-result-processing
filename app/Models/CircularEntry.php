<?php

namespace App\Models;

use App\Enums\CadreType;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CircularEntry extends ExaminationModel
{
    protected $fillable = [
        'cadre_serial', 'sub_serial', 'cadre_code', 'sub_cadre_code', 'effective_code', 'cadre_type',
        'cadre_name_snapshot', 'cadre_name_bn_snapshot', 'post_name_snapshot', 'post_name_bn_snapshot',
        'post_count', 'status', 'note', 'source', 'version',
    ];

    protected function casts(): array
    {
        return [
            'cadre_serial' => 'integer', 'sub_serial' => 'integer', 'cadre_code' => 'integer',
            'sub_cadre_code' => 'integer', 'effective_code' => 'integer', 'cadre_type' => CadreType::class,
            'post_count' => 'integer', 'version' => 'integer',
        ];
    }

    public function bachelorSubjects(): HasMany
    {
        return $this->hasMany(CircularEntryBachelorSubject::class, 'circular_entry_id');
    }

    public function prsSubjects(): HasMany
    {
        return $this->hasMany(CircularEntryPrs::class, 'circular_entry_id');
    }
}
