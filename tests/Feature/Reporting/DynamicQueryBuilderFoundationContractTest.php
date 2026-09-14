<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryBuilderFoundationContractTest extends TestCase
{
    public function test_reporting_hub_exposes_dynamic_query_builder_as_independent_capability(): void
    {
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $landing = file_get_contents(resource_path('views/reporting/index.blade.php'));

        self::assertStringContainsString("/dynamic-query", $routes);
        self::assertStringContainsString("dynamic-query.preview", $routes);
        self::assertStringContainsString('Dynamic Query Builder', $landing);
        self::assertStringContainsString('Cross-Module Reporting', $landing);
        self::assertStringContainsString('Research &amp; Statistics Section Reporting', $landing);
    }

    public function test_semantic_registry_hides_database_contract_from_browser_metadata(): void
    {
        $registry = file_get_contents(app_path('Services/Reporting/DynamicQuery/SemanticFieldRegistry.php'));
        $config = file_get_contents(config_path('dynamic-reports.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));

        self::assertStringContainsString('browserFields()', $registry);
        self::assertStringNotContainsString("'expression' =>", substr($registry, strpos($registry, 'public function browserFields')));
        self::assertStringContainsString("'candidate.reg'", $config);
        self::assertStringContainsString("'candidate.birth_date'", $config);
        self::assertStringContainsString("'merit.general_position'", $config);
        self::assertStringContainsString("'merit.written_track'", $config);
        self::assertStringContainsString("'allocation.cadre_code'", $config);
        self::assertStringContainsString("'allocation.cadre_type'", $config);
        self::assertStringContainsString('SemanticFieldRegistry', $controller);
    }

    public function test_preview_is_bounded_and_query_compiler_uses_only_whitelisted_semantic_fields(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString("'preview_sizes' => [5, 10, 20, 50, 100]", $config);
        self::assertStringContainsString("'default_preview_size' => 10", $config);
        self::assertStringContainsString('$this->registry->get', $compiler);
        self::assertStringContainsString('->limit($size)', $compiler);
        self::assertStringContainsString("distinct()->count('registrations.id')", $compiler);
        self::assertStringNotContainsString('name="sql"', strtolower($view));
        self::assertStringContainsString('Database structure and SQL remain internal.', $view);
    }

    public function test_report_presentation_controls_are_present_from_the_first_slice(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));
        foreach (['Report Mode', 'Report Title', 'Display column name', 'Preview Rows', 'Serial number', 'Page number', 'Generation timestamp', 'Sorting', 'Grouping &amp; Aggregates'] as $label) {
            self::assertStringContainsString($label, $view);
        }
    }

    public function test_summary_mode_supports_controlled_grouping_and_aggregate_functions(): void
    {
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));

        self::assertStringContainsString("'groups'", $controller);
        self::assertStringContainsString("count_distinct,sum,avg,min,max", $controller);
        self::assertStringContainsString('summaryPreview', $compiler);
        self::assertStringContainsString("'count_distinct' => 'COUNT'", $compiler);
        self::assertStringContainsString("'sum' => 'SUM'", $compiler);
        self::assertStringContainsString("'avg' => 'AVG'", $compiler);
    }
}
