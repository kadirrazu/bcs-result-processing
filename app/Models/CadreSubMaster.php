<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reusable child/sub-cadre code whose cadre identity is inherited from its parent. */
final class CadreSubMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_cadre_id', 'sub_cadre_code', 'sub_cadre_abbr',
        'post_name', 'post_name_bn', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parent_cadre_id' => 'integer',
            'sub_cadre_code' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parentCadre(): BelongsTo
    {
        return $this->belongsTo(CadreMaster::class, 'parent_cadre_id');
    }

    public function getParentCadreCodeAttribute(): ?int
    {
        return $this->relationLoaded('parentCadre')
            ? $this->parentCadre?->cadre_code
            : $this->parentCadre()->value('cadre_code');
    }
}
