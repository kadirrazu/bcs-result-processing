<?php

namespace App\Enums;

/** Administrative lifecycle state of a candidate registration. */
enum RegistrationStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Withheld = 'withheld';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
