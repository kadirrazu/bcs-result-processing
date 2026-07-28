<?php

namespace App\Data;

use App\Enums\CadreType;

/** Validated data for a central cadre master record. */
final readonly class CadreMasterData
{
    public function __construct(public int $code, public string $abbr, public string $title, public string $titleBn, public CadreType $type, public int $displayOrder, public bool $isActive) {}

    /** @param array<string, mixed> $validated */
    public static function fromValidated(array $validated, bool $isActive): self
    {
        return new self((int) $validated['cadre_code'], strtoupper(trim((string) $validated['cadre_abbr'])), trim((string) $validated['cadre_title']), trim((string) $validated['cadre_title_bn']), CadreType::from((string) $validated['cadre_type']), (int) $validated['display_order'], $isActive);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['cadre_code' => $this->code, 'cadre_abbr' => $this->abbr, 'cadre_title' => $this->title, 'cadre_title_bn' => $this->titleBn, 'cadre_type' => $this->type, 'display_order' => $this->displayOrder, 'is_active' => $this->isActive];
    }
}
