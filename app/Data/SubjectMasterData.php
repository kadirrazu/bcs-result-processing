<?php

namespace App\Data;

/** Shared validated data for bachelor and post-related subject master records. */
final readonly class SubjectMasterData
{
    public function __construct(public string $code, public string $name, public bool $isActive) {}

    /** @param array<string, mixed> $validated */
    public static function fromValidated(array $validated, bool $isActive): self
    {
        return new self(strtoupper(trim((string) $validated['subject_code'])), trim((string) $validated['subject_name']), $isActive);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['subject_code' => $this->code, 'subject_name' => $this->name, 'is_active' => $this->isActive];
    }
}
