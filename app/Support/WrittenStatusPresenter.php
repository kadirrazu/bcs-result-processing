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
            in_array($status, ['approved', 'generated', 'completed', 'processing_ready', 'marks_imported'], true)
                => 'bg-green-lt text-green',
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
        $value = self::value($status);

        return $value === null || $value === ''
            ? $fallback
            : ucwords(str_replace('_', ' ', $value));
    }
}
