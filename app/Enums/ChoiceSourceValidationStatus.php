<?php

namespace App\Enums;

enum ChoiceSourceValidationStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Valid => 'Valid',
            self::Invalid => 'Invalid',
        };
    }
}
