<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class CircularAuthorityPreview extends ExaminationModel
{
    protected $fillable = [
        'version', 'dataset_hash', 'file_path', 'generated_by', 'generated_at', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'generated_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(CircularConfirmation::class, 'authority_preview_id');
    }
}
