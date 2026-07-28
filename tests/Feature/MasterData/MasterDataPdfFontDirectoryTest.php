<?php

namespace Tests\Feature\MasterData;

use App\Services\MasterDataExport\MasterDataPdfFontResolver;
use Tests\TestCase;

final class MasterDataPdfFontDirectoryTest extends TestCase
{
    public function test_font_resolver_creates_dompdf_font_directory(): void
    {
        $directory = storage_path('fonts');

        if (is_dir($directory)) {
            foreach (glob($directory.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        app(MasterDataPdfFontResolver::class);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->assertDirectoryExists($directory);
        $this->assertDirectoryIsWritable($directory);
    }
}
