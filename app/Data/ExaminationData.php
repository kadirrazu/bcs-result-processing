<?php

namespace App\Data;

use App\Enums\ExaminationStatus;

/**
 * Validated application data for creating or updating an examination registry entry.
 */
final readonly class ExaminationData
{
    public function __construct(
        public int $bcsNumber,
        public string $name,
        public string $slug,
        public string $databaseName,
        public ExaminationStatus $status,
        public bool $isEnabled,
    ) {}

    /**
     * Build the DTO from validated request data.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated, bool $isEnabled): self
    {
        return new self(
            bcsNumber: (int) $validated['bcs_number'],
            name: trim((string) $validated['name']),
            slug: trim((string) $validated['slug']),
            databaseName: trim((string) $validated['database_name']),
            status: ExaminationStatus::from((string) $validated['status']),
            isEnabled: $isEnabled,
        );
    }

    /**
     * Convert the DTO to model attributes.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bcs_number' => $this->bcsNumber,
            'name' => $this->name,
            'slug' => $this->slug,
            'database_name' => $this->databaseName,
            'status' => $this->status,
            'is_enabled' => $this->isEnabled,
        ];
    }
}
