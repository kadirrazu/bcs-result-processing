<?php

namespace App\Enums;

/** Optional examination classification used for current/future processing rules. */
enum ExaminationType: string
{
    case General = 'GENERAL';
    case Special = 'SPECIAL';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General BCS',
            self::Special => 'Special BCS',
        };
    }
}
