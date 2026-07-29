<?php

namespace App\Enums;

/**
 * Administrative lifecycle state of a registration record.
 */
enum RegistrationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
