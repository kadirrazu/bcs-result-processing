<?php

namespace App\Services\Registrations;

/**
 * Preserve optional university source codes without making them processing blockers.
 *
 * University is retained for future reporting only. A code missing from the central
 * master therefore remains on the registration while a non-blocking warning is
 * recorded in both the candidate comment and the import audit trail.
 */
final class RegistrationUniversityCodePolicy
{
    private const WARNING_PREFIX = '[IMPORT WARNING] Invalid University Code: ';

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, bool> $validUniversityCodes
     * @return array{attributes: array<string, mixed>, warnings: list<string>}
     */
    public function apply(array $attributes, array $validUniversityCodes): array
    {
        $code = $attributes['university_code'] ?? null;

        if ($code === null || isset($validUniversityCodes[(string) $code])) {
            return ['attributes' => $attributes, 'warnings' => []];
        }

        $warning = self::WARNING_PREFIX.$code;
        $attributes['comment'] = $this->appendUniqueComment(
            isset($attributes['comment']) ? (string) $attributes['comment'] : null,
            $warning,
        );

        return [
            'attributes' => $attributes,
            'warnings' => [$warning],
        ];
    }

    private function appendUniqueComment(?string $comment, string $warning): string
    {
        $comment = trim((string) $comment);

        if ($comment === '') {
            return $warning;
        }

        $lines = preg_split('/\R/u', $comment) ?: [$comment];
        if (in_array($warning, array_map('trim', $lines), true)) {
            return $comment;
        }

        // Use a canonical LF separator so comments and tests remain stable across Windows and Linux.
        return $comment."\n".$warning;
    }
}
