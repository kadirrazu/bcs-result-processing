<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA6QueuedReusableExportContinuationContractTest extends TestCase
{
    public function test_a6_heavy_exports_use_centralized_queue_and_json_polling(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $progress = file_get_contents(resource_path('views/allocation/a6/export-show.blade.php'));

        self::assertStringContainsString('ProcessAllocationA6Export::dispatch', $controller);
        self::assertStringContainsString("config('allocation.queue', 'imports')", $job);
        self::assertStringContainsString('requireReadyStrict()', $job);
        self::assertStringContainsString("Route::get('/a6/exports/runs/{exportRun}/status'", $routes);
        self::assertStringContainsString('fetch(box.dataset.statusUrl', $progress);
        self::assertStringContainsString('progress-bar-animated', $progress);
    }

    public function test_txt_export_contains_locked_total_and_zero_allocation_cadre_contract(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));

        self::assertStringContainsString('TOTAL ALLOCATED = ', $service);
        self::assertStringContainsString('NO ELIGIBLE CANDIDATE', $service);
        self::assertStringContainsString('TOTAL = ', $service);
        self::assertStringNotContainsString("allocated'] < 1", $service);
    }

    public function test_dynamic_excel_builder_and_shared_reporting_primitives_are_present(): void
    {
        $catalog = file_get_contents(app_path('Services/Allocation/AllocationA6ExcelFieldCatalog.php'));
        $builder = file_get_contents(resource_path('views/allocation/a6/excel-builder.blade.php'));
        $writer = file_get_contents(app_path('Services/Reporting/SpreadsheetReportWriter.php'));
        $store = file_get_contents(app_path('Services/Reporting/ReportExportFileStore.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $reset = file_get_contents(config_path('development-module-reset.php'));

        foreach (['Registration','Preliminary','Written','Viva','Tabulation','Choice','Merit','Allocation','A5 Validity'] as $module) {
            self::assertStringContainsString($module, $catalog);
        }
        self::assertStringContainsString('selected_fields', $controller);
        self::assertStringContainsString('selected_field_labels', $controller);
        self::assertStringContainsString('Queue Custom Excel Export', $builder);
        self::assertStringContainsString('final class SpreadsheetReportWriter', $writer);
        self::assertStringContainsString('final class ReportExportFileStore', $store);
        self::assertStringContainsString("['table' => 'reporting_export_runs', 'column' => 'module', 'values' => ['allocation_a6']]", $reset);
    }
}
