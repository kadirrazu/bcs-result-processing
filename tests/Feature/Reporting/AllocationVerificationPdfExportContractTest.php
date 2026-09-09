<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class AllocationVerificationPdfExportContractTest extends TestCase
{
    public function test_verification_listing_zero_eligible_and_queued_pdf_contract(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/CadreSectionReportingController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationVerificationPdfExport.php'));
        $pdf = file_get_contents(app_path('Reports/Pdf/AllocationVerificationPdfReport.php'));
        $listing = file_get_contents(resource_path('views/reporting/cadre-section/cadre-wise-index.blade.php'));
        $report = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $progress = file_get_contents(resource_path('views/reporting/allocation-verification/export-show.blade.php'));
        $sharedStyle = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table-style.blade.php'));
        $sharedTable = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));
        $routes = file_get_contents(base_path('routes/reporting.php'));

        self::assertStringContainsString('cadre-search', $listing);
        self::assertStringContainsString('cadre-filter', $listing);
        self::assertStringContainsString('eligible_count', $listing);
        self::assertStringContainsString('> 0', $listing);
        self::assertStringContainsString('Export PDF', $report);
        self::assertStringContainsString('@page{size:legal landscape;margin:0.5in}', $sharedStyle);
        self::assertStringContainsString('ProcessAllocationVerificationPdfExport', $controller);
        self::assertStringContainsString("'module' => self::EXPORT_MODULE", $controller);
        self::assertStringContainsString("'page_size' => 'Legal'", $controller);
        self::assertStringContainsString("? 'Portrait' : 'Landscape'", $controller);
        self::assertStringContainsString("'margin_inches' => 0.5", $controller);
        self::assertStringContainsString("'format' => 'Legal'", $pdf);
        self::assertStringContainsString("'orientation' => 'L'", $pdf);
        self::assertStringContainsString("'margin_left' => 12.7", $pdf);
        self::assertStringContainsString("'margin_right' => 12.7", $pdf);
        self::assertStringContainsString("'margin_top' => 12.7", $pdf);
        self::assertStringContainsString("'margin_bottom' => 12.7", $pdf);
        self::assertStringContainsString('AllocationVerificationReportService $reports', $job);
        self::assertStringContainsString('ReportExportFileStore $files', $job);
        self::assertStringContainsString('disposition_hash', $job);
        self::assertStringContainsString('verification-export-progress', $progress);
        self::assertSame(substr_count($progress, '@if('), substr_count($progress, '@endif'));
        self::assertStringContainsString("@php\n    $isRunning = in_array", $progress);

        $pdfView = file_get_contents(resource_path('views/reports/pdf/allocation-verification.blade.php'));
        self::assertStringContainsString("_verification-table',", $pdfView);
        self::assertStringContainsString('Higher Choice<br>Missed Reason', $sharedTable);
        self::assertStringContainsString('Bachelor Subject &amp;<br>PRS', $sharedTable);
        self::assertStringNotContainsString('@if(!$loop->last); @else. @endif', $pdfView);
        self::assertStringContainsString('cadre.verification.exports.status', $routes);
        self::assertStringContainsString('cadre.verification.exports.download', $routes);
    }
}
