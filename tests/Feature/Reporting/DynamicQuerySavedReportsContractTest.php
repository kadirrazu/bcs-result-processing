<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQuerySavedReportsContractTest extends TestCase
{
    public function test_saved_reports_persist_semantic_definitions_and_version_changes(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_09_13_210000_create_reporting_dynamic_saved_reports.php'));
        $service = file_get_contents(app_path('Services/Reporting/DynamicQuery/SavedDynamicReportService.php'));

        self::assertStringContainsString("create('reporting_dynamic_saved_reports'", $migration);
        self::assertStringContainsString("create('reporting_dynamic_run_history'", $migration);
        self::assertStringContainsString("\$table->json('definition')", $migration);
        self::assertStringContainsString("\$table->json('definition_snapshot')", $migration);
        self::assertStringContainsString('canonicalDefinition', $service);
        self::assertStringContainsString("'version' => (int) \$report->version + 1", $service);
        self::assertStringNotContainsString('DB::raw', $service);
    }

    public function test_saved_report_routes_and_ui_support_load_update_save_as_new_delete_and_history(): void
    {
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));

        foreach (['dynamic-query.saved.save', 'dynamic-query.saved.show', 'dynamic-query.saved.destroy', 'dynamic-query.history'] as $routeName) {
            self::assertStringContainsString($routeName, $routes);
        }
        foreach (['Saved Reports', 'Save as New', 'Recent Executions', 'dq-load-report', 'dq-delete-report'] as $text) {
            self::assertStringContainsString($text, $view);
        }
        self::assertStringContainsString('recordPreview', $controller);
        self::assertStringContainsString('duration_ms', $controller);
        self::assertStringContainsString('col-12', $view);
        self::assertStringContainsString('Live Preview', $view);
    }

    public function test_dynamic_query_workspace_uses_the_locked_full_width_workflow_order(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        $saved = strpos($view, '<h3 class="card-title">Saved Reports</h3>');
        $step1 = strpos($view, '<h3 class="card-title">Data &amp; Fields</h3>');
        $step2 = strpos($view, '<h3 class="card-title">Conditions</h3>');
        $step3 = strpos($view, '<h3 class="card-title">Sorting &amp; Report Configuration</h3>');
        $preview = strpos($view, '<h3 class="card-title">Live Preview</h3>');
        $audit = strpos($view, '<h3 class="card-title">Recent Executions</h3>');

        foreach ([$saved, $step1, $step2, $step3, $preview, $audit] as $position) {
            self::assertNotFalse($position);
        }

        self::assertTrue($saved < $step1);
        self::assertTrue($step1 < $step2);
        self::assertTrue($step2 < $step3);
        self::assertTrue($step3 < $preview);
        self::assertTrue($preview < $audit);
        self::assertGreaterThanOrEqual(6, substr_count($view, '<div class="col-12">'));
        self::assertStringNotContainsString('<div class="col-xl-4">', $view);
        self::assertStringNotContainsString('<div class="col-xl-8">', $view);
    }

    public function test_step_one_fields_are_grouped_module_by_module_in_full_width_responsive_grids(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString('dq-field-module border rounded p-3 mb-3', $view);
        self::assertStringContainsString('dq-field-chip-list', $view);
        self::assertStringContainsString('dq-field-chip', $view);
        self::assertStringContainsString('data-module=', $view);
        self::assertStringContainsString('Selected Report Fields', $view);
        self::assertStringContainsString('dq-selected-chips', $view);
        self::assertStringContainsString('dq-selected-chip', $view);
        self::assertStringContainsString('id="dq-clear-fields"', $view);
        self::assertStringContainsString('Clear Selected Fields', $view);
        self::assertStringContainsString('${a.length} field', $view);
        self::assertStringNotContainsString('form-check-input dq-field', $view);
        self::assertStringNotContainsString('col-12 col-sm-6 col-lg-3', $view);
    }

    public function test_selected_fields_support_explicit_display_order_labels_and_click_to_remove(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString('Column Display Order &amp; Labels', $view);
        self::assertStringContainsString('dq-move-left', $view);
        self::assertStringContainsString('dq-move-right', $view);
        self::assertStringContainsString('moveSelected(id,-1)', $view);
        self::assertStringContainsString('moveSelected(id,1)', $view);
        self::assertStringContainsString('dq-selected-remove', $view);
        self::assertStringContainsString('This exact order is used by Preview, XLSX and PDF.', $view);
    }

    public function test_run_history_uses_the_migration_table_name_and_degrades_gracefully_when_unavailable(): void
    {
        $model = file_get_contents(app_path('Models/ReportingDynamicRunHistory.php'));
        $service = file_get_contents(app_path('Services/Reporting/DynamicQuery/SavedDynamicReportService.php'));

        self::assertStringContainsString("protected \$table = 'reporting_dynamic_run_history'", $model);
        self::assertStringContainsString("hasTable('reporting_dynamic_run_history')", $service);
        self::assertStringContainsString('historyTableReady()', $service);
    }

    public function test_saved_definitions_never_accept_database_expressions_or_sql_fragments(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/DynamicQuery/SavedDynamicReportService.php'));

        self::assertStringContainsString('$this->registry->get', $service);
        self::assertStringContainsString('canonicalConditions', $service);
        self::assertStringContainsString('canonicalLabels', $service);
        self::assertStringNotContainsString("['expression']", $service);
        self::assertStringNotContainsString('DB::raw', $service);
    }

    public function test_saved_report_audit_can_record_preview_and_export_execution_types(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/DynamicQuery/SavedDynamicReportService.php'));
        $xlsx = file_get_contents(app_path('Jobs/ProcessDynamicQueryXlsxExport.php'));
        $pdf = file_get_contents(app_path('Jobs/ProcessDynamicQueryPdfExport.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString('public function recordExecution', $service);
        self::assertStringContainsString("['preview', 'xlsx_export', 'pdf_export']", $service);
        self::assertStringContainsString("'xlsx_export'", $xlsx);
        self::assertStringContainsString("'pdf_export'", $pdf);
        self::assertStringContainsString("xlsx_export:'XLSX Export'", $view);
        self::assertStringContainsString("pdf_export:'PDF Export'", $view);
    }
}
