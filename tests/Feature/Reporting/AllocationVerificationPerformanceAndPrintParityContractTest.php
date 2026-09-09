<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class AllocationVerificationPerformanceAndPrintParityContractTest extends TestCase
{
    public function test_verification_uses_authority_cache_shared_table_and_locked_page_formats(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $browser = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/allocation-verification.blade.php'));
        $sharedTable = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));
        $sharedStyle = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table-style.blade.php'));
        $pdfBuilder = file_get_contents(app_path('Reports/Pdf/AllocationVerificationPdfReport.php'));
        $serialBrowser = file_get_contents(resource_path('views/reporting/allocation-verification/cadre-serial-merit.blade.php'));
        $serialPdf = file_get_contents(resource_path('views/reports/pdf/cadre-serial-merit-verification-page.blade.php'));

        self::assertStringContainsString('Cache::remember', $service);
        self::assertStringContainsString('disposition_hash', $service);
        self::assertStringContainsString('disposition_revision', $service);
        self::assertStringContainsString('$allocations->keys()', $service);

        self::assertStringContainsString("_verification-table',", $browser);
        self::assertStringContainsString("_verification-table',", $pdf);
        self::assertStringContainsString('white-space:nowrap', $sharedStyle);
        self::assertStringContainsString('avr-history-line', $sharedStyle);
        self::assertStringContainsString('avr-merit-line', $sharedStyle);
        self::assertStringContainsString('line-height:1.68', $sharedStyle);
        self::assertStringContainsString('@page{size:legal landscape;margin:0.5in}', $sharedStyle);
        self::assertStringContainsString("'format' => 'Legal'", $pdfBuilder);
        self::assertStringContainsString("'orientation' => 'L'", $pdfBuilder);

        self::assertStringContainsString('size: A4 portrait', $serialBrowser);
        self::assertStringContainsString('csm-gap', $serialBrowser);
        self::assertStringContainsString('class="gap"', $serialPdf);
        self::assertStringContainsString("'orientation' => 'P'", $pdfBuilder);
        self::assertStringContainsString('$mpdf->AddPage(\'P\')', $pdfBuilder);
    }
}
