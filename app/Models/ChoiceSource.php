<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChoiceSource extends ExaminationModel
{
    protected $table = 'choice_validation_sources';
    protected $guarded = [];
    protected function casts(): array { return ['source_snapshot' => 'array', 'raw_choice_count' => 'integer', 'source_version' => 'integer', 'approved_at' => 'datetime']; }
    public function items(): HasMany { return $this->hasMany(ChoiceSourceItem::class, 'choice_validation_source_id'); }
}
