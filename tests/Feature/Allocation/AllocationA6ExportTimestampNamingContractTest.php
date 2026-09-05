<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

class AllocationA6ExportTimestampNamingContractTest extends TestCase
{
    public function test_a6_download_names_use_shared_timestamp_suffix_contract(): void
    {
        $path = app_path('Services/Allocation/AllocationA6ExportService.php');
        $source = file_get_contents($path);

        $this->assertStringContainsString('timestampedDownloadName', $source);
        $this->assertStringContainsString("now()->format('Ymd-His')", $source);

        foreach (['txt', 'zip', 'xlsx', 'docx'] as $extension) {
            $this->assertStringContainsString("'".$extension."'", $source);
        }

        $this->assertStringContainsString(
            "\$this->timestampedDownloadName(\$code.'-'.\$abbr, 'txt')",
            $source
        );
    }
}
