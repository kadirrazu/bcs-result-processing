<?php

namespace App\Enums;

/**
 * Lifecycle state of an examination registry entry.
 */
enum ExaminationStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Archived = 'archived';

    /**
     * Return the human-readable status label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Archived => 'Archived',
        };
    }
}
