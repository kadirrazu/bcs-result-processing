<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryBuilderCurrentFieldDiscoveryContractTest extends TestCase
{
    public function test_step_one_field_discovery_is_filtered_by_current_authority_sources(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));
        $registry = file_get_contents(app_path('Services/Reporting/DynamicQuery/SemanticFieldRegistry.php'));
        $authority = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicReportAuthority.php'));

        self::assertStringContainsString('DynamicReportAuthority $authority', $controller);
        self::assertStringContainsString('browserFields($authority->browserReadySources())', $controller);
        self::assertStringContainsString('public function browserFields(?array $readySources = null)', $registry);
        self::assertStringContainsString("! in_array(\$source, \$readySources, true)", $registry);
        self::assertStringContainsString('public function browserReadySources(): array', $authority);
        self::assertStringContainsString("\$ready = ['registrations'];", $authority);
        self::assertStringContainsString("'merit' => 'merit_run_id'", $authority);
        self::assertStringContainsString("'allocation' => 'allocation_a5_run_id'", $authority);
    }

    public function test_circular_browser_fields_follow_the_same_allocation_dependency_as_query_execution(): void
    {
        $authority = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicReportAuthority.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        self::assertStringContainsString("resolve(['circular'])", $authority);
        self::assertStringContainsString("! empty(\$circular['circular_version'])", $authority);
        self::assertStringContainsString("! empty(\$authority['allocation_a5_run_id'])", $authority);
        self::assertStringContainsString("in_array('circular', \$sources, true) && ! in_array('allocation', \$sources, true)", $compiler);
    }
}
