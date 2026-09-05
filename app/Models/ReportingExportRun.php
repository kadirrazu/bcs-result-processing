<?php

namespace App\Models;

final class ReportingExportRun extends ExaminationModel
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'source_snapshot' => 'array',
            'progress_percent' => 'integer',
            'progress_current' => 'integer',
            'progress_total' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }
}
