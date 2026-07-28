<?php

namespace App\Enums;

/** Cadre classifications used by common and technical processing flows. */
enum CadreType: string
{
    case General = 'GG';
    case Technical = 'TT';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Technical => 'Technical / Professional',
        };
    }
}
