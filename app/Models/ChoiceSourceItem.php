<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ChoiceSourceItem extends ExaminationModel
{
    protected $table = 'choice_validation_source_items';
    protected $guarded = [];
    public function source(): BelongsTo { return $this->belongsTo(ChoiceSource::class, 'choice_validation_source_id'); }
}
