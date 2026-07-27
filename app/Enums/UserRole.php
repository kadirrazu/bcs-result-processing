<?php

namespace App\Enums;

/**
 * Roles available to authenticated central application users.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';

    /**
     * Return the human-readable role label used by forms and reports.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Operator => 'Operator',
            self::Viewer => 'Viewer',
        };
    }
}
