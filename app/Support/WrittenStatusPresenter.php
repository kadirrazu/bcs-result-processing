<?php

namespace App\Support;

use BackedEnum;
use UnitEnum;

final class WrittenStatusPresenter
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
        $status = strtolower((string) self::value($status));

        return match (true) {
            in_array($status, ['approved', 'generated', 'completed', 'result_finalized', 'marks_imported'], true)
                => 'bg-green-lt text-green',
            in_array($status, ['processing_ready'], true)
                => 'bg-teal-lt text-teal',
            in_array($status, ['running', 'processing', 'staging', 'validating', 'approving'], true)
                => 'bg-blue-lt text-blue',
            in_array($status, ['queued', 'validation_queued', 'approval_queued'], true)
                => 'bg-azure-lt text-azure',
            in_array($status, ['failed', 'invalid'], true)
                => 'bg-red-lt text-red',
            in_array($status, ['warning', 'stale', 'reopened'], true)
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
            'marks_imported' => 'Marks imported',
            'reconciliation_generated' => 'Attendance summary ready',
            'processing_ready' => 'Ready for final review',
            'result_finalized' => 'Result finalized',
            'reopened' => 'Needs reprocessing',
            'queued' => 'Waiting in queue',
            'running' => 'In progress',
            'completed' => 'Completed',
            'approved' => 'Approved',
            'generated' => 'Ready',
            'pending' => 'Pending',
            'failed' => 'Needs attention',
            'staging' => 'Reading source file',
            'validation_queued' => 'Waiting for validation',
            'validating' => 'Checking data',
            'approval_queued' => 'Waiting for approval',
            'approving' => 'Merging approved rows',
            'warning' => 'Needs review',
            'invalid' => 'Invalid',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    public static function taskLabel(?string $type): string
    {
        return match ($type) {
            'written_finalization' => 'Final Written review',
            'written_rules' => 'Paper crash and track processing',
            default => 'Written background task',
        };
    }
}
