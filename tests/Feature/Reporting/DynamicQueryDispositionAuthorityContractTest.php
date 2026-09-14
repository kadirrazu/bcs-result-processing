<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryDispositionAuthorityContractTest extends TestCase
{
    public function test_publication_disposition_exports_bind_to_a5_5_revision_and_hash(): void
    {
        $authority = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicReportAuthority.php'));
        $xlsx = file_get_contents(app_path('Jobs/ProcessDynamicQueryXlsxExport.php'));
        $pdf = file_get_contents(app_path('Jobs/ProcessDynamicQueryPdfExport.php'));

        $this->assertStringContainsString('AllocationResultDispositionState', $authority);
        $this->assertStringContainsString("'allocation_disposition_revision' => null", $authority);
        $this->assertStringContainsString("'allocation_disposition_hash' => null", $authority);
        $this->assertStringContainsString("in_array('allocation_disposition', \$sources, true)", $authority);
        $this->assertStringContainsString("allocation_disposition_revision", $xlsx);
        $this->assertStringContainsString("allocation_disposition_hash", $xlsx);
        $this->assertStringContainsString("allocation_disposition_revision", $pdf);
        $this->assertStringContainsString("allocation_disposition_hash", $pdf);
    }
}
