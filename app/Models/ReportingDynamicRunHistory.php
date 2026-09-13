<?php

namespace App\Models;

final class ReportingDynamicRunHistory extends ExaminationModel
{
    public $timestamps = false;

    protected $table = 'reporting_dynamic_run_history';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'definition_snapshot' => 'array',
            'saved_report_id' => 'integer',
            'saved_report_version' => 'integer',
            'matching_count' => 'integer',
            'preview_size' => 'integer',
            'duration_ms' => 'integer',
            'executed_by' => 'integer',
            'executed_at' => 'datetime',
        ];
    }
}
