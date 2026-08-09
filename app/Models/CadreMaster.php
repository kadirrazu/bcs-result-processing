<?php

namespace App\Models;

use App\Enums\CadreType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Central reusable main-cadre identity. Examination-specific vacancies live in Circular. */
final class CadreMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'cadre_code', 'cadre_abbr', 'cadre_name', 'cadre_name_bn',
        'post_name', 'post_name_bn', 'cadre_type', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cadre_code' => 'integer',
            'cadre_type' => CadreType::class,
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subCadres(): HasMany
    {
        return $this->hasMany(CadreSubMaster::class, 'parent_cadre_id');
    }
}
