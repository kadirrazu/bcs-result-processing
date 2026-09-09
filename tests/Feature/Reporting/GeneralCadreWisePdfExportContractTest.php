<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class GeneralCadreWisePdfExportContractTest extends TestCase
{
    public function test_general_cadre_pdf_reuses_existing_queue_and_cadre_specific_filename_scope(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/CadreSectionReportingController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationVerificationPdfExport.php'));
        $pdf = file_get_contents(app_path('Reports/Pdf/AllocationVerificationPdfReport.php'));

        self::assertStringContainsString("queuePdf($request, 'general-cadre', $cadreCode", $controller);
        self::assertStringContainsString('ProcessAllocationVerificationPdfExport::dispatch', $controller);

        self::assertStringContainsString('$reports->build($type, $a5, $cadreCode)', $job);
        self::assertStringContainsString('$pdf->generate($data, (string) $exam->name, $type, $cadreCode)', $job);

        self::assertStringContainsString("['general-cadre', 'technical-cadre']", $pdf);
        self::assertStringContainsString("$reportType.'-'.$cadreCode", $pdf);
    }
}
