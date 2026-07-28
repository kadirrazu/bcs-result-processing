<?php

namespace App\Reports\Shared;

/**
 * Resolve a Bengali font profile that mPDF can parse reliably.
 *
 * Windows Nirmala UI contains OpenType data that can trigger an undefined
 * glyph-key error in mPDF's TTFontFile parser for some Bengali text. Therefore
 * it is deliberately not auto-selected. By default, the report uses mPDF's
 * bundled FreeSerif font with complex-script OTL enabled.
 */
final class BanglaPdfFontResolver
{
    /**
     * @return array{
     *     family: string,
     *     directory: string|null,
     *     regular: string,
     *     bold: string|null,
     *     source: string
     * }
     */
    public function resolve(): array
    {
        $configuredPath = config('master-data-export.bangla_font_path');

        if (is_string($configuredPath) && $configuredPath !== '') {
            $custom = $this->resolveCustomFont($configuredPath);

            if ($custom !== null) {
                return $custom;
            }
        }

        return [
            'family' => 'freeserifbengali',
            'directory' => null,
            'regular' => 'FreeSerif.ttf',
            'bold' => 'FreeSerifBold.ttf',
            'source' => 'mPDF bundled FreeSerif',
        ];
    }

    /**
     * @return array{family: string, directory: string, regular: string, bold: null, source: string}|null
     */
    private function resolveCustomFont(string $candidate): ?array
    {
        $path = str_replace('\\', '/', trim($candidate));

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        // Nirmala UI is intentionally excluded because mPDF can fail while
        // compiling its GPOS/GSUB data (TTFontFile undefined glyph key).
        if (str_starts_with(strtolower(pathinfo($path, PATHINFO_FILENAME)), 'nirmala')) {
            return null;
        }

        if (! $this->supportsUnicodeBengaliShaping($path)) {
            return null;
        }

        return [
            'family' => (string) config('master-data-export.bangla_font_family', 'banglareport'),
            'directory' => dirname($path),
            'regular' => basename($path),
            'bold' => null,
            'source' => $path,
        ];
    }

    /** Bengali needs both layout tables and a Bengali script declaration. */
    private function supportsUnicodeBengaliShaping(string $path): bool
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        return str_contains($contents, 'GSUB')
            && str_contains($contents, 'GPOS')
            && (str_contains($contents, 'bng2') || str_contains($contents, 'beng'));
    }
}
