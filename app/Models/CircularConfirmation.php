<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CircularConfirmation extends ExaminationModel
{
    protected $fillable = [
        'authority_preview_id', 'version', 'dataset_hash', 'confirmed_by', 'confirmed_at', 'confirmation_notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function preview(): BelongsTo
    {
        return $this->belongsTo(CircularAuthorityPreview::class, 'authority_preview_id');
    }
}
