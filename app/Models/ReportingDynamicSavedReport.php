<?php

namespace App\Models;

final class ReportingDynamicSavedReport extends ExaminationModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'version' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}
