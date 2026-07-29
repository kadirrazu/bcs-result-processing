<?php

namespace App\Enums;

/**
 * Validation state used before a registration can enter Preliminary processing.
 */
enum RegistrationValidationStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
