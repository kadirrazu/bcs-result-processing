<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class CadreSerialMeritVerificationContractTest extends TestCase
{
    public function test_cadre_serial_merit_basis_report_contract(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/CadreSectionReportingController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationVerificationPdfExport.php'));
        $pdf = file_get_contents(app_path('Reports/Pdf/AllocationVerificationPdfReport.php'));
        $index = file_get_contents(resource_path('views/reporting/cadre-section/index.blade.php'));
        $view = file_get_contents(resource_path('views/reporting/allocation-verification/cadre-serial-merit.blade.php'));
        $pdfView = file_get_contents(resource_path('views/reports/pdf/cadre-serial-merit-verification.blade.php'));
        $pdfPageView = file_get_contents(resource_path('views/reports/pdf/cadre-serial-merit-verification-page.blade.php'));
        $pdfStyleView = file_get_contents(resource_path('views/reports/pdf/cadre-serial-merit-verification-style.blade.php'));
        $routes = file_get_contents(base_path('routes/reporting.php'));

        self::assertStringContainsString('buildCadreSerialMerit', $service);
        self::assertStringContainsString('applyPublishedOnly', $service);
        self::assertStringContainsString("'allocation_a4_results.registration_id'", $service);
        self::assertStringContainsString('general_merit_position', $service);
        self::assertStringContainsString('technical_merit_position', $service);
        self::assertStringContainsString('allocation_basis', $service);
        self::assertStringContainsString('$rowsPerGroup = 25', $service);
        self::assertStringContainsString('->chunk($rowsPerGroup * 3)', $service);
        self::assertStringContainsString("'serial' => $index + 1", $service);
        self::assertStringContainsString("'fallback_merit_position'", $service);
        self::assertStringContainsString("'total_allocated'] > 0", $service);
        self::assertStringContainsString("'total_post' => (int) $capacity->sanctioned_posts", $service);
        self::assertStringContainsString("'total_allocated' => $candidateRows->count()", $service);

        self::assertStringContainsString('Cadre-wise Serial, Merit &amp; Allocation Basis Report', $index);
        self::assertStringContainsString('cadreSerialMerit', $controller);
        self::assertStringContainsString('queueCadreSerialMeritPdf', $controller);
        self::assertStringContainsString("'cadre-serial-merit'", $controller);

        self::assertStringContainsString('@page', $view);
        self::assertStringContainsString('size: A4 portrait', $view);
        self::assertStringContainsString('margin: 0.5in', $view);
        self::assertStringContainsString('Total Post:', $view);
        self::assertStringContainsString('Total Allocated:', $view);
        self::assertStringContainsString('Allocation Basis', $view);
        self::assertStringContainsString('$groupIndex < 3', $view);
        self::assertStringContainsString('colspan="11"', $view);

        self::assertStringContainsString("if ($type === 'cadre-serial-merit')", $job);
        self::assertStringContainsString('generateCadreSerialMerit', $job);
        self::assertStringContainsString('HTMLParserMode::HEADER_CSS', $pdf);
        self::assertStringContainsString('HTMLParserMode::HTML_BODY', $pdf);
        self::assertStringContainsString('$mpdf->AddPage(\'P\')', $pdf);
        self::assertStringContainsString('cadre-serial-merit-verification-style', $pdf);
        self::assertStringContainsString('cadre-serial-merit-verification-page', $pdf);
        self::assertStringNotContainsString("view('reports.pdf.cadre-serial-merit-verification', $data", $pdf);
        self::assertStringContainsString("'format' => 'A4'", $pdf);
        self::assertStringContainsString("'orientation' => 'P'", $pdf);
        self::assertStringContainsString('cadre-serial-merit-verification', $pdf);
        self::assertStringContainsString('Allocation Basis', $pdfView);
        self::assertStringContainsString('Allocation Basis', $pdfPageView);
        self::assertStringContainsString('$groupIndex < 3', $pdfPageView);
        self::assertStringContainsString('table-layout: fixed', $pdfStyleView);
        self::assertStringContainsString('class="gap"', $pdfPageView);

        self::assertStringContainsString('cadre-serial-merit-report', $routes);
        self::assertStringContainsString('cadre.verification.cadre-serial-merit.pdf', $routes);
    }
}
