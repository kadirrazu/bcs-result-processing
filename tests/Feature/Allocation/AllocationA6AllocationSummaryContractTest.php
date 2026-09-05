<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA6AllocationSummaryContractTest extends TestCase
{
    public function test_allocation_summary_is_read_only_circular_ordered_and_exportable(): void
    {
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $summary = file_get_contents(app_path('Services/Allocation/AllocationA6SummaryService.php'));
        $export = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));
        $view = file_get_contents(resource_path('views/allocation/a6/summary.blade.php'));
        $pdf = file_get_contents(app_path('Reports/Pdf/AllocationA6SummaryPdfReport.php'));

        self::assertStringContainsString("name('a6.summary')", $routes);
        self::assertStringContainsString("name('a6.summary.export')", $routes);
        self::assertStringContainsString('AllocationA6SummaryService $summary', $controller);
        self::assertStringContainsString("'allocation_summary'", $controller);

        self::assertStringContainsString('AllocationA4SeatLedger::query()', $summary);
        self::assertStringContainsString('cadre_name_snapshot', $summary);
        self::assertStringContainsString('post_name_snapshot', $summary);
        self::assertStringContainsString("'cff_converted'", $summary);
        self::assertStringContainsString("'em_converted'", $summary);
        self::assertStringContainsString("'phc_converted'", $summary);
        self::assertStringContainsString("'merit_rest'", $summary);
        self::assertStringContainsString("'total_vacant'", $summary);
        self::assertStringContainsString("'%02d-%08d-%08d-%08d'", $summary);

        self::assertStringContainsString('In-depth Allocation Seat Summary', $view);
        self::assertStringContainsString('Merit Pool', $view);
        self::assertStringContainsString('NM Converted', $view);
        self::assertStringContainsString('Phase-2 Movement', $view);
        self::assertStringContainsString('Queue Excel', $view);
        self::assertStringContainsString('Queue PDF', $view);

        self::assertStringContainsString('allocationSummaryXlsx', $export);
        self::assertStringContainsString("\$scope === 'allocation_summary'", $job);
        self::assertStringContainsString("'PDF' => \$this->generatePdf", $job);
        self::assertStringContainsString("'orientation' => 'L'", $pdf);
        self::assertStringContainsString("format('Ymd-His')", $pdf);
    }

    public function test_short_allocation_summary_keeps_only_overall_columns_and_uses_same_queue_pipeline(): void
    {
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $view = file_get_contents(resource_path('views/allocation/a6/summary-short.blade.php'));
        $summary = file_get_contents(app_path('Services/Allocation/AllocationA6SummaryService.php'));
        $export = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));
        $pdf = file_get_contents(app_path('Reports/Pdf/AllocationA6SummaryPdfReport.php'));

        self::assertStringContainsString("name('a6.summary.short')", $routes);
        self::assertStringContainsString("name('a6.summary.short.export')", $routes);
        self::assertStringContainsString('function shortSummary(', $controller);
        self::assertStringContainsString("'allocation_summary_short'", $controller);
        self::assertStringContainsString('Short Allocation Summary', $view);
        self::assertStringContainsString('Total Post', $view);
        self::assertStringContainsString('Total Allocated', $view);
        self::assertStringContainsString('Total Vacant', $view);
        self::assertStringNotContainsString('Merit Pool', $view);
        self::assertStringNotContainsString('NM Converted', $view);
        self::assertStringContainsString('shortExcelHeaders', $summary);
        self::assertStringContainsString('allocationShortSummaryXlsx', $export);
        self::assertStringContainsString("\$scope === 'allocation_summary_short'", $job);
        self::assertStringContainsString("\$short ? 'reports.pdf.allocation-a6-summary-short'", $pdf);
    }

    public function test_allocation_landing_a6_card_uses_same_container_width_contract(): void
    {
        $landing = file_get_contents(resource_path('views/allocation/index.blade.php'));

        self::assertStringContainsString('<div class="container-xl">', $landing);
        self::assertStringContainsString('id="allocation-a6-card"', $landing);
    }
}
