<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryExportFoundationContractTest extends TestCase
{
    public function test_step_one_can_clear_all_selected_fields_without_resetting_the_whole_report(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString('id="dq-clear-fields"', $view);
        self::assertStringContainsString('Clear Selected Fields', $view);
        self::assertStringContainsString('selected.clear();Object.keys(labelOverrides)', $view);
        self::assertStringContainsString('renderSelected();', $view);
        self::assertStringNotContainsString("document.getElementById('dq-clear-fields').onclick=()=>applyDefinition", $view);
    }


    public function test_live_preview_can_be_cleared_without_resetting_report_definition(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString('id="dq-clear-preview"', $view);
        self::assertStringContainsString('Clear Preview', $view);
        self::assertStringContainsString("document.getElementById('dq-count').textContent='Not calculated'", $view);
        self::assertStringContainsString("document.getElementById('dq-preview-table').innerHTML=''", $view);
        self::assertStringContainsString('Preview cleared. Report configuration is unchanged.', $view);
        self::assertStringNotContainsString("document.getElementById('dq-clear-preview').onclick=()=>applyDefinition", $view);
    }

    public function test_dynamic_query_xlsx_export_uses_the_shared_queue_and_private_export_store(): void
    {
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessDynamicQueryXlsxExport.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $writer = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryXlsxWriter.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        foreach (['dynamic-query.export.xlsx', 'dynamic-query.export.status', 'dynamic-query.export.download'] as $routeName) {
            self::assertStringContainsString($routeName, $routes);
        }
        self::assertStringContainsString("'module' => 'dynamic_query'", $controller);
        self::assertStringContainsString('ProcessDynamicQueryXlsxExport::dispatch', $controller);
        self::assertStringContainsString("->where('module', 'dynamic_query')", $job);
        self::assertStringContainsString("outputPath('dynamic-query'", $job);
        self::assertStringContainsString('exportDataset', $compiler);
        self::assertStringContainsString('->cursor()', $compiler);
        self::assertStringNotContainsString('->get()->map(function ($row)', substr($compiler, strpos($compiler, 'public function exportDataset')));
        self::assertStringContainsString('Generated:', $writer);
        self::assertStringContainsString('Page &P of &N', $writer);
        self::assertStringContainsString('id="dq-export-xlsx"', $view);
        self::assertStringContainsString('Download XLSX', $view);
    }

    public function test_dynamic_query_pdf_export_reuses_semantic_definition_order_and_shared_queue(): void
    {
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessDynamicQueryPdfExport.php'));
        $report = file_get_contents(app_path('Reports/Pdf/DynamicQueryPdfReport.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString('dynamic-query.export.pdf', $routes);
        self::assertStringContainsString('ProcessDynamicQueryPdfExport::dispatch', $controller);
        self::assertStringContainsString("->where('export_type', 'PDF')", $job);
        self::assertStringContainsString("outputPath('dynamic-query'", $job);
        self::assertStringContainsString('generateToFile', $job);
        self::assertStringContainsString('Destination::FILE', $report);
        self::assertStringContainsString("storage_path('app/private/mpdf')", $report);
        self::assertStringContainsString('show_page_number', $report);
        self::assertStringContainsString('show_timestamp', $report);
        self::assertStringContainsString("report_title", $report);
        self::assertStringContainsString('foreach ($columns as $column)', $report);
        self::assertStringContainsString('id="dq-export-pdf"', $view);
        self::assertStringContainsString('Download ${format}', $view);
    }

    public function test_queued_export_is_bound_to_semantic_authority_ids_before_generation(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessDynamicQueryXlsxExport.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        self::assertStringContainsString('authoritySnapshot', $controller);
        self::assertStringContainsString("collect(\$authority)->except('warnings')->all()", $controller);
        foreach (['preliminary_finalization_run_id', 'written_processing_run_id', 'viva_processing_run_id', 'tabulation_run_id', 'merit_run_id', 'allocation_a5_run_id'] as $key) {
            self::assertStringContainsString($key, $job);
        }
        self::assertStringContainsString('Queued report source changed before generation', $job);
        self::assertStringContainsString('public function authoritySnapshot', $compiler);
    }
}
