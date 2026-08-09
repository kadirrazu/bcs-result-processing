<?php

namespace App\Data;

use App\Enums\CadreType;

final readonly class CadreMasterData
{
    public function __construct(
        public int $code,
        public string $abbr,
        public string $name,
        public string $nameBn,
        public ?string $postName,
        public ?string $postNameBn,
        public CadreType $type,
        public int $displayOrder,
        public bool $isActive,
    ) {}

    public static function fromValidated(array $validated, bool $isActive): self
    {
        $nullable = static fn (mixed $value): ?string => trim((string) ($value ?? '')) === '' ? null : trim((string) $value);

        return new self(
            (int) $validated['cadre_code'],
            strtoupper(trim((string) $validated['cadre_abbr'])),
            trim((string) $validated['cadre_name']),
            trim((string) $validated['cadre_name_bn']),
            $nullable($validated['post_name'] ?? null),
            $nullable($validated['post_name_bn'] ?? null),
            CadreType::from((string) $validated['cadre_type']),
            (int) $validated['display_order'],
            $isActive,
        );
    }

    public function toArray(): array
    {
        return [
            'cadre_code' => $this->code,
            'cadre_abbr' => $this->abbr,
            'cadre_name' => $this->name,
            'cadre_name_bn' => $this->nameBn,
            'post_name' => $this->postName,
            'post_name_bn' => $this->postNameBn,
            'cadre_type' => $this->type,
            'display_order' => $this->displayOrder,
            'is_active' => $this->isActive,
        ];
    }
}
