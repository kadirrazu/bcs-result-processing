<?php

namespace App\Support;

use BackedEnum;
use UnitEnum;

final class PreliminaryStatusPresenter
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
            in_array($value, ['approved', 'generated', 'completed', 'result_finalized', 'mark_imported', 'cutoff_set', 'distribution_generated', 'reconciliation_generated'], true)
                => 'bg-green-lt text-green',
            in_array($value, ['result_finalizing', 'running', 'staging', 'validating', 'approving'], true)
                => 'bg-blue-lt text-blue',
            in_array($value, ['queued', 'validation_queued', 'approval_queued'], true)
                => 'bg-azure-lt text-azure',
            in_array($value, ['failed', 'invalid', 'identity_conflict'], true)
                => 'bg-red-lt text-red',
            in_array($value, ['warning', 'stale', 'reopened', 'review_required'], true)
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
            'mark_imported' => 'Marks imported',
            'reconciliation_generated' => 'Attendance summary ready',
            'distribution_generated' => 'Mark distribution ready',
            'cutoff_set' => 'Cut-off approved',
            'result_finalizing' => 'Final result is being prepared',
            'result_finalized' => 'Result finalized',
            'reopened' => 'Needs reprocessing',
            'approved' => 'Approved',
            'generated' => 'Ready',
            'completed' => 'Completed',
            'queued' => 'Waiting in queue',
            'running' => 'In progress',
            'staging' => 'Reading source file',
            'validation_queued' => 'Waiting for validation',
            'validating' => 'Checking data',
            'approval_queued' => 'Waiting for approval',
            'approving' => 'Merging approved rows',
            'failed' => 'Needs attention',
            'warning' => 'Needs review',
            'review_required' => 'Review required',
            'pending' => 'Pending',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }
}
