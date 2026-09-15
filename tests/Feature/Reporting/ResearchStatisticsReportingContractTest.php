<?php

namespace Tests\Feature\Reporting;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResearchStatisticsReportingContractTest extends TestCase
{
    #[Test]
    public function research_statistics_contract_is_wired_to_dedicated_reports_and_pdf_export(): void
    {
        $routes=file_get_contents(base_path('routes/reporting.php'));
        $service=file_get_contents(app_path('Services/Reporting/ResearchStatisticsService.php'));
        $controller=file_get_contents(app_path('Http/Controllers/Reporting/ResearchStatisticsController.php'));
        $landing=file_get_contents(resource_path('views/reporting/research-statistics/index.blade.php'));
        $show=file_get_contents(resource_path('views/reporting/research-statistics/show.blade.php'));
        $pdf=file_get_contents(app_path('Reports/Pdf/ResearchStatisticsPdfReport.php'));
        $pdfView=file_get_contents(resource_path('views/reports/pdf/research-statistics.blade.php'));

        $this->assertStringContainsString("research-statistics/{report}", $routes);
        $this->assertStringContainsString("research-statistics/{report}/pdf", $routes);
        $this->assertStringContainsString('public function catalog()', $service);
        $this->assertStringContainsString('public function report(string $key)', $service);
        $this->assertStringContainsString('PreliminaryProcessingStatus::ResultFinalized', $service);
        $this->assertStringContainsString('WrittenProcessingStatus::ResultFinalized', $service);
        $this->assertStringContainsString('applyPublishedOnly', $service);
        $this->assertStringContainsString('EDUCATION_PARENT_CADRES = [610, 620, 630, 640, 660]', $service);
        foreach (["'Below 21'","'21-23'","'24-26'","'27-29'","'30 and above'"] as $bucket) $this->assertStringContainsString($bucket,$service);
        $this->assertStringContainsString('Top 10 Educational Institutions by Recommended Candidates', $service);
        $this->assertStringContainsString("route('examination-reports.research-statistics.show'", $landing);
        $this->assertStringContainsString("route('examination-reports.research-statistics.pdf'", $show);
        $this->assertStringContainsString('ResearchStatisticsPdfReport', $controller);
        $this->assertStringContainsString('Str::random(10)', $pdf);
        $this->assertStringContainsString('REPORT ID:', $pdf);
        $this->assertStringContainsString('REPORT GENERATED ON:', $pdf);
        $this->assertStringContainsString('Page {PAGENO} of {nbpg}', $pdf);
        $this->assertStringContainsString('Total — Top 10 Institutions', $pdfView);
        $table=file_get_contents(resource_path('views/reporting/research-statistics/_statistics-table.blade.php'));
        $this->assertStringContainsString('Serial', $table);
        $this->assertStringContainsString('text-center align-middle', $table);
        $this->assertStringContainsString('<tfoot>', $table);
        $this->assertStringContainsString('<tfoot>', $pdfView);
        $this->assertStringContainsString('td.num,td.serial{text-align:center}', $pdfView);
    }
}
