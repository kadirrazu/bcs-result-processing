<?php

namespace App\Models;

use App\Enums\CadreType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Central catalogue entry from which examination-specific cadre snapshots are created. */
final class CadreMaster extends Model
{
    use HasFactory;

    protected $fillable = ['cadre_code', 'cadre_abbr', 'cadre_title', 'cadre_title_bn', 'cadre_type', 'display_order', 'is_active'];

    protected function casts(): array
    {
        return ['cadre_code' => 'integer', 'cadre_type' => CadreType::class, 'display_order' => 'integer', 'is_active' => 'boolean'];
    }
}
