<?php

namespace App\Support\Registrations;

/**
 * Consistent UI formatting for Registration values backed by central code masters.
 *
 * Registration rows intentionally store stable source/master codes. UI surfaces
 * should therefore display both the code and its resolved human-readable title
 * so an operator can visually reconcile source data without losing context.
 */
final class RegistrationReferencePresenter
{
    public static function codeTitle(
        mixed $code,
        ?string $title,
        string $unmappedLabel = 'Unmapped master code',
    ): string {
        $codeText = trim((string) ($code ?? ''));

        if ($codeText === '') {
            return '—';
        }

        $titleText = trim((string) ($title ?? ''));

        return $codeText.' - '.($titleText !== '' ? $titleText : $unmappedLabel);
    }
}
