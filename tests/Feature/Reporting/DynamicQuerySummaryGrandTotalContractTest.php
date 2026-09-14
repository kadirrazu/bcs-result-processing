<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQuerySummaryGrandTotalContractTest extends TestCase
{
    public function test_summary_totals_are_calculated_from_full_filtered_dataset_before_preview_limit(): void
    {
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        $this->assertStringContainsString(
            '$totals = $groups !== []'."\n".'            ? $this->summaryAggregateTotals(clone $base, $aggregates, $metadata)',
            $compiler
        );
        $this->assertStringContainsString('private function summaryAggregateTotals(', $compiler);
        $this->assertStringContainsString("'total_label' => \$totals !== [] ? 'GRAND TOTAL' : null", $compiler);
        $this->assertStringContainsString("'count_distinct' => 'COUNT'", $compiler);
        $this->assertStringContainsString("'sum' => 'SUM'", $compiler);
        $this->assertStringContainsString("'avg' => 'AVG'", $compiler);
        $this->assertStringContainsString("'min' => 'MIN'", $compiler);
        $this->assertStringContainsString("'max' => 'MAX'", $compiler);
    }

    public function test_preview_xlsx_and_pdf_render_the_same_summary_footer_contract(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));
        $xlsx = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryXlsxWriter.php'));
        $pdf = file_get_contents(app_path('Reports/Pdf/DynamicQueryPdfReport.php'));
        $xlsxJob = file_get_contents(app_path('Jobs/ProcessDynamicQueryXlsxExport.php'));
        $pdfJob = file_get_contents(app_path('Jobs/ProcessDynamicQueryPdfExport.php'));

        $this->assertStringContainsString('data.total_label', $view);
        $this->assertStringContainsString('data.totals', $view);

        $this->assertStringContainsString('array $totals = []', $xlsx);
        $this->assertStringContainsString('$totalLabel', $xlsx);
        $this->assertStringContainsString("getFont()->setBold(true)", $xlsx);

        $this->assertStringContainsString('array $totals = []', $pdf);
        $this->assertStringContainsString("class=\"grand-total\"", $pdf);

        $this->assertStringContainsString("(array) (\$dataset['totals'] ?? [])", $xlsxJob);
        $this->assertStringContainsString("(array) (\$dataset['totals'] ?? [])", $pdfJob);
    }
}
