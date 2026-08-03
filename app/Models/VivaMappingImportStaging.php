<?php

namespace App\Models;

final class VivaMappingImportStaging extends ExaminationModel
{
    protected $table = 'viva_mapping_import_staging';

    protected $fillable = [
        'batch_id', 'source_row', 'raw_payload', 'registration_id', 'written_result_id',
        'user_id', 'reg', 'code', 'validation_status', 'validation_errors', 'validation_warnings',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array', 'validation_errors' => 'array', 'validation_warnings' => 'array',
        ];
    }
}
