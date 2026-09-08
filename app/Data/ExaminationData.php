<?php

namespace App\Data;

use App\Enums\ExaminationStatus;
use App\Enums\ExaminationType;

/** Validated application data for creating or updating an examination registry entry. */
final readonly class ExaminationData
{
    public function __construct(
        public int $bcsNumber,
        public string $name,
        public string $slug,
        public string $databaseName,
        public ?ExaminationType $bcsType,
        public ?string $advertisementDate,
        public ?string $ageCalculationDate,
        public ExaminationStatus $status,
        public bool $isEnabled,
        public bool $isCompleted,
    ) {}

    /** @param array<string, mixed> $validated */
    public static function fromValidated(array $validated, bool $isEnabled, bool $isCompleted): self
    {
        $type = trim((string) ($validated['bcs_type'] ?? ''));

        return new self(
            bcsNumber: (int) $validated['bcs_number'],
            name: trim((string) $validated['name']),
            slug: trim((string) $validated['slug']),
            databaseName: trim((string) $validated['database_name']),
            bcsType: $type !== '' ? ExaminationType::from($type) : null,
            advertisementDate: self::nullableDate($validated['advertisement_date'] ?? null),
            ageCalculationDate: self::nullableDate($validated['age_calculation_date'] ?? null),
            status: ExaminationStatus::from((string) $validated['status']),
            isEnabled: $isEnabled,
            isCompleted: $isCompleted,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'bcs_number' => $this->bcsNumber,
            'name' => $this->name,
            'slug' => $this->slug,
            'database_name' => $this->databaseName,
            'bcs_type' => $this->bcsType?->value,
            'advertisement_date' => $this->advertisementDate,
            'age_calculation_date' => $this->ageCalculationDate,
            'status' => $this->status,
            'is_enabled' => $this->isEnabled,
            'is_completed' => $this->isCompleted,
        ];
    }

    private static function nullableDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
