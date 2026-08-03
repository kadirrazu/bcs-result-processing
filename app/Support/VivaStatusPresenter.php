<?php

namespace App\Support;

use BackedEnum;
use UnitEnum;

final class VivaStatusPresenter
{
    public static function value(mixed $status): ?string
    {
        if ($status instanceof BackedEnum) {
            return (string) $status->value;
        }
        if ($status instanceof UnitEnum) {
            return $status->name;
        }

        return $status === null ? null : (string) $status;
    }

    public static function badgeClass(mixed $status): string
    {
        $value = strtolower((string) self::value($status));

        return match (true) {
            in_array($value, ['approved', 'completed', 'result_finalized', 'mapping_imported', 'board_data_imported', 'reconciliation_generated'], true)
                => 'bg-green-lt text-green',
            in_array($value, ['processing_ready'], true)
                => 'bg-teal-lt text-teal',
            in_array($value, ['running', 'staging', 'validating', 'approving', 'result_finalizing'], true)
                => 'bg-blue-lt text-blue',
            in_array($value, ['queued', 'validation_queued', 'approval_queued'], true)
                => 'bg-azure-lt text-azure',
            in_array($value, ['failed', 'invalid'], true)
                => 'bg-red-lt text-red',
            in_array($value, ['warning', 'reopened', 'stale'], true)
                => 'bg-yellow-lt text-yellow',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public static function label(mixed $status, string $fallback = 'Pending'): string
    {
        $value = strtolower((string) self::value($status));
        if ($value === '') {
            return $fallback;
        }

        return match ($value) {
            'not_started' => 'Not started',
            'mapping_imported' => 'Candidate mapping ready',
            'board_data_imported' => 'Board data ready',
            'reconciliation_generated' => 'Attendance summary ready',
            'processing_ready' => 'Ready for final review',
            'result_finalizing' => 'Final review in progress',
            'result_finalized' => 'Viva processing finalized',
            'reopened' => 'Needs reprocessing',
            'queued' => 'Waiting in queue',
            'staging' => 'Reading source file',
            'validation_queued' => 'Waiting for validation',
            'validating' => 'Checking data',
            'approval_queued' => 'Waiting for approval',
            'approving' => 'Merging approved rows',
            'approved' => 'Approved',
            'warning' => 'Needs review',
            'invalid' => 'Invalid',
            'failed' => 'Needs attention',
            'completed' => 'Completed',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }
}
