<?php

namespace App\Services\MasterDataExport;

use RuntimeException;

/** Locate a Unicode-capable Bangla font and prepare Dompdf's font cache. */
final class MasterDataPdfFontResolver
{
    /** @return array{family: string, data_uri: string, source: string} */
    public function resolve(): array
    {
        $this->ensureDompdfFontDirectory();

        $path = collect($this->candidatePaths())
            ->filter()
            ->map(static fn (string $path): string => str_replace('\\', '/', $path))
            ->first(static fn (string $path): bool => is_file($path) && is_readable($path));

        if (! $path) {
            throw new RuntimeException(
                'No readable Unicode Bangla font was found. Put a verified Unicode Nikosh.ttf at '.
                storage_path('app/fonts/Nikosh.ttf').
                ', install Windows Nirmala UI, or set MASTER_DATA_PDF_BANGLA_FONT_PATH in .env.'
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Bangla font could not be read from [{$path}].");
        }

        return [
            'family' => (string) config('master-data-export.bangla_font_family', 'BanglaPdfFont'),
            'data_uri' => 'data:font/truetype;base64,'.base64_encode($contents),
            'source' => $path,
        ];
    }

    /** Ensure php-font-lib can create its .ufm and cache files. */
    private function ensureDompdfFontDirectory(): void
    {
        $directory = storage_path('fonts');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create Dompdf font directory [{$directory}].");
        }

        if (! is_writable($directory)) {
            throw new RuntimeException(
                "Dompdf font directory is not writable [{$directory}]. ".
                'Grant the web server user write permission to storage/fonts.'
            );
        }
    }

    /**
     * Prefer an explicitly supplied verified Unicode Nikosh file. Windows
     * Nirmala UI is intentionally checked before the system Nikosh file,
     * because many legacy Nikosh installations are ANSI/non-Unicode builds.
     *
     * @return list<string|null>
     */
    private function candidatePaths(): array
    {
        return [
            config('master-data-export.bangla_font_path'),
            storage_path('app/fonts/Nikosh.ttf'),
            storage_path('app/fonts/nikosh.ttf'),
            public_path('fonts/Nikosh.ttf'),
            public_path('fonts/nikosh.ttf'),
            'C:/Windows/Fonts/Nirmala.ttf',
            'C:/Windows/Fonts/NirmalaS.ttf',
            'C:/Windows/Fonts/NirmalaB.ttf',
            'C:/Windows/Fonts/Nikosh.ttf',
            'C:/Windows/Fonts/nikosh.ttf',
        ];
    }
}
