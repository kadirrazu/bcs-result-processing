<?php

namespace App\Enums;

/**
 * Candidate cadre category received from the registration source file.
 *
 * The numeric values are part of the external data contract and therefore
 * must not be changed without a coordinated source-format migration.
 */
enum CadreCategory: int
{
    case General = 1;
    case Technical = 2;
    case GeneralAndTechnical = 3;

    /**
     * Return the stable short code used by downstream processing stages.
     */
    public function code(): string
    {
        return match ($this) {
            self::General => 'GG',
            self::Technical => 'TT',
            self::GeneralAndTechnical => 'GT',
        };
    }

    /**
     * Return a human-readable label for forms and reports.
     */
    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Technical => 'Technical',
            self::GeneralAndTechnical => 'General & Technical',
        };
    }

    /**
     * Return values suitable for Laravel validation rules.
     *
     * @return list<int>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $category): int => $category->value,
            self::cases(),
        );
    }
}
