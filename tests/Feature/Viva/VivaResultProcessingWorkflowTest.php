<?php

namespace Tests\Feature\Viva;

use Tests\TestCase;

final class VivaResultProcessingWorkflowTest extends TestCase
{
    public function test_viva_processing_contract_is_config_driven_versioned_and_confidential(): void
    {
        $service = file_get_contents(app_path('Services/Viva/VivaResultProcessingService.php'));
        $routes = file_get_contents(base_path('routes/viva.php'));
        $export = file_get_contents(app_path('Services/Viva/VivaInternalResultExportService.php'));
        $migration = file_get_contents(database_path('examination-migrations/2026_08_05_223000_create_viva_result_processing_workflow.php'));

        $this->assertStringContainsString('$this->rules->passMark()', $service);
        $this->assertStringContainsString('ABSENT_IN_VIVA', $service);
        $this->assertStringContainsString('BELOW_VIVA_PASS_MARK', $service);
        $this->assertStringContainsString('processing_snapshot', $service);
        $this->assertStringContainsString('processing_version', $migration);
        $this->assertStringContainsString("'/results/export/xlsx'", $routes);
        $this->assertStringContainsString('INTERNAL ADMINISTRATIVE USE ONLY', $export);
        $this->assertStringNotContainsString('txt', strtolower($routes));
    }
}
