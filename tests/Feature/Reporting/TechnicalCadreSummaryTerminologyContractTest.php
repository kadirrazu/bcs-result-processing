<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class TechnicalCadreSummaryTerminologyContractTest extends TestCase
{
    public function test_summary_includes_other_and_no_cadre_allocation_counts(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $summary = file_get_contents(resource_path('views/reporting/allocation-verification/_technical-cadre-summary.blade.php'));
        $pdfSummary = file_get_contents(resource_path('views/reports/pdf/_technical-cadre-summary.blade.php'));

        self::assertStringContainsString('allocated_in_other_cadres', $service);
        self::assertStringContainsString('not_allocated_anywhere', $service);
        self::assertStringContainsString('Allocated in Other Cadres', $summary);
        self::assertStringContainsString('Not Allocated in Any Cadre', $summary);
        self::assertStringContainsString('Allocated in Other Cadres', $pdfSummary);
        self::assertStringContainsString('Not Allocated in Any Cadre', $pdfSummary);
    }
}
