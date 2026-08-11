<?php

namespace App\Support;

final class ChoiceValidationStatusPresenter
{
    public static function value(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            return strtolower((string) $status->value);
        }

        return strtolower(trim((string) ($status ?? 'not_started')));
    }

    public static function label(mixed $status): string
    {
        return match (self::value($status)) {
            'not_started' => 'Not started',
            'queued' => 'Queued',
            'processing' => 'Staging',
            'staged' => 'Staged',
            'validation_queued' => 'Validation queued',
            'validating' => 'Validating',
            'validated' => 'Validated',
            'partially_approved', 'source_partially_approved' => 'Partially approved',
            'approved', 'source_approved' => 'Approved',
            'validation_completed' => 'Validation completed',
            'failed', 'validation_failed' => 'Needs attention',
            'running' => 'Running',
            'completed' => 'Completed',
            'stale', 'outdated' => 'Outdated',
            default => ucwords(str_replace('_', ' ', self::value($status))),
        };
    }
 
    public static function resultLabel(mixed $status): string
    {
        return match (self::value($status)) {
            'valid' => 'Valid',
            'no_valid_choices' => 'No Valid Choice',
            'not_applicable_due_to_fail_in_viva' => 'Not Applicable — Failed in Viva',
            'not_applicable_due_to_inactive_viva_result' => 'Not Applicable — Inactive Viva Result',
            'not_applicable_due_to_missing_viva_result' => 'Not Applicable — Missing Viva Result',
            'not_applicable_due_to_unresolved_written_track' => 'Not Applicable — Unresolved Written Track',
            'not_applicable' => 'Not Applicable',
            default => ucwords(str_replace('_', ' ', self::value($status))),
        };
    }

    public static function resultBadgeClass(mixed $status): string
    {
        return match (self::value($status)) {
            'valid' => 'bg-green-lt text-green',
            'no_valid_choices' => 'bg-yellow-lt text-yellow',
            'not_applicable_due_to_fail_in_viva' => 'bg-red-lt text-red',
            'not_applicable_due_to_inactive_viva_result' => 'bg-orange-lt text-orange',
            'not_applicable_due_to_missing_viva_result',
            'not_applicable_due_to_unresolved_written_track' => 'bg-purple-lt text-purple',
            default => 'bg-secondary-lt text-secondary',
        };
    }

    public static function badgeClass(mixed $status): string
    {
        return match (self::value($status)) {
            'approved', 'source_approved', 'completed', 'validation_completed' => 'bg-green-lt text-green',
            'partially_approved', 'source_partially_approved', 'staged' => 'bg-yellow-lt text-yellow',
            'queued', 'validation_queued' => 'bg-azure-lt text-azure',
            'processing', 'validating', 'running' => 'bg-blue-lt text-blue',
            'validated' => 'bg-teal-lt text-teal',
            'failed', 'validation_failed' => 'bg-red-lt text-red',
            'stale', 'outdated' => 'bg-orange-lt text-orange',
            default => 'bg-secondary-lt text-secondary',
        };
    }
}
