<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryFinalStabilizationContractTest extends TestCase
{
    public function test_preview_size_validation_is_config_driven_and_supports_50_and_100(): void
    {
        $config = require base_path('config/dynamic-reports.php');
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));

        $this->assertSame([5, 10, 20, 50, 100], $config['preview_sizes']);
        $this->assertStringContainsString("Rule::in((array) config('dynamic-reports.preview_sizes'", $controller);
        $this->assertStringNotContainsString("'in:5,10,20'", $controller);
    }

    public function test_operator_requests_use_shared_non_json_error_reader(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        $this->assertStringContainsString("async function readJsonResponse", $view);
        $this->assertStringContainsString("new DOMParser()", $view);
        $this->assertStringContainsString("Unable to queue \${format} export.", $view);
        $this->assertStringContainsString("Unable to refresh execution history.", $view);
        $this->assertStringContainsString("Unable to load saved report.", $view);
        $this->assertStringContainsString("Unable to delete saved report.", $view);
    }

    public function test_preview_failure_exposes_safe_database_codes_and_reference_not_sql(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        $this->assertStringContainsString("'database_error_code' => \$sqlState", $controller);
        $this->assertStringContainsString("'driver_error_code' => \$driverCode", $controller);
        $this->assertStringContainsString("'error_reference' => \$reference", $controller);
        $this->assertStringContainsString("data.database_error_code", $view);
        $this->assertStringContainsString("data.error_reference", $view);
    }

    public function test_builder_has_explicit_full_reset_without_deleting_saved_reports(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        $this->assertStringContainsString('id="dq-reset-builder"', $view);
        $this->assertStringContainsString("currentSavedReportId=null", $view);
        $this->assertStringContainsString("Builder reset.", $view);
        $this->assertStringContainsString("applyDefinition({preview_size:Number(root.dataset.defaultPreview)", $view);
    }
}
