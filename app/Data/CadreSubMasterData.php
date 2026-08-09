<?php

namespace App\Data;

final readonly class CadreSubMasterData
{
    public function __construct(
        public int $parentCadreId,
        public int $code,
        public ?string $abbr,
        public string $postName,
        public string $postNameBn,
        public int $displayOrder,
        public bool $isActive,
    ) {}

    public static function fromValidated(array $validated, bool $isActive): self
    {
        $abbr = strtoupper(trim((string) ($validated['sub_cadre_abbr'] ?? '')));

        return new self(
            (int) $validated['parent_cadre_id'],
            (int) $validated['sub_cadre_code'],
            $abbr === '' ? null : $abbr,
            trim((string) $validated['post_name']),
            trim((string) $validated['post_name_bn']),
            (int) $validated['display_order'],
            $isActive,
        );
    }

    public function toArray(): array
    {
        return [
            'parent_cadre_id' => $this->parentCadreId,
            'sub_cadre_code' => $this->code,
            'sub_cadre_abbr' => $this->abbr,
            'post_name' => $this->postName,
            'post_name_bn' => $this->postNameBn,
            'display_order' => $this->displayOrder,
            'is_active' => $this->isActive,
        ];
    }
}
