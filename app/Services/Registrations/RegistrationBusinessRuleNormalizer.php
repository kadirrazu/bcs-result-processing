<?php

namespace App\Services\Registrations;

use App\Enums\CadreCategory;

/**
 * Apply registration rules shared by manual entry, spreadsheet imports and future APIs.
 *
 * Keeping these rules in one service prevents entry points from producing different
 * candidate states for the same input.
 */
final class RegistrationBusinessRuleNormalizer
{
    /**
     * @param array<string, mixed> $attributes
     * @return array{attributes: array<string, mixed>, warnings: list<string>}
     */
    public function normalize(array $attributes): array
    {
        $warnings = [];
        $category = (int) ($attributes['cadre_category'] ?? 0);

        if ($category === CadreCategory::General->value
            && ($attributes['post_related_subject_code'] ?? null) !== null) {
            $attributes['post_related_subject_code'] = null;
            $warnings[] = 'GG candidate supplied a post-related subject; the value was normalized to NULL.';
        }

        return ['attributes' => $attributes, 'warnings' => $warnings];
    }
}
