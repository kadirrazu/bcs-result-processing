<?php

namespace Tests\Feature\Circular;

use Tests\TestCase;

final class CircularC6ContractTest extends TestCase
{
    public function test_c6_finalized_dataset_contract_files_exist(): void
    {
        $this->assertFileExists(app_path('Services/Circular/CircularFinalizedDatasetService.php'));
        $this->assertFileExists(app_path('Reports/Pdf/CircularFinalSummaryPdfReport.php'));
        $this->assertFileExists(app_path('Reports/Excel/CircularFinalSummaryExcelReport.php'));
        $this->assertFileExists(resource_path('views/circular/final-report.blade.php'));
        $this->assertFileExists(resource_path('views/reports/pdf/circular-final-summary.blade.php'));
        $this->assertFileExists(base_path('docs/circular/CIRCULAR_MODULE_LOCKED_v1.0.md'));
    }

    public function test_c6_routes_are_registered_in_circular_route_file(): void
    {
        $routes = file_get_contents(base_path('routes/circular.php'));

        $this->assertStringContainsString("name('final-report.index')", $routes);
        $this->assertStringContainsString("name('final-report.pdf')", $routes);
        $this->assertStringContainsString("name('final-report.excel')", $routes);
    }

    public function test_finalized_dataset_service_enforces_same_version_gate(): void
    {
        $service = file_get_contents(app_path('Services/Circular/CircularFinalizedDatasetService.php'));

        $this->assertStringContainsString('CircularProcessingStatus::Finalized', $service);
        $this->assertStringContainsString('(int) $state->current_version === $version', $service);
        $this->assertStringContainsString('(int) $state->approved_version === $version', $service);
        $this->assertStringContainsString('(int) $state->confirmed_version === $version', $service);
    }

    public function test_circular_reset_registry_contains_c6_relevant_migration_owned_tables(): void
    {
        $tables = collect(config('development-module-reset.modules.circular.tables', []));

        // The canonical exact reset-registry contract is enforced by
        // DevelopmentModuleResetRegistryTest, which discovers tables directly
        // from database/examination-migrations. C6 only verifies the Circular
        // migration-owned tables introduced/required by the current workflow.
        foreach ([
            'circular_authority_previews',
            'circular_confirmations',
            'circular_import_batches',
            'circular_import_staging',
        ] as $table) {
            $this->assertTrue($tables->contains($table), "Circular reset registry is missing [{$table}].");
        }
    }

    public function test_c6_does_not_define_upstream_module_routes_or_schema(): void
    {
        $routeFile = file_get_contents(base_path('routes/circular.php'));
        $service = file_get_contents(app_path('Services/Circular/CircularFinalizedDatasetService.php'));

        foreach (['registration', 'preliminary', 'written', 'viva'] as $module) {
            $this->assertStringNotContainsString("prefix('{$module}')", $routeFile);
            $this->assertStringNotContainsString("{$module}_processing_states", $service);
        }
    }
}
