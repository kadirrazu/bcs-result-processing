<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryPreviewHardeningContractTest extends TestCase
{
    public function test_preview_sizes_include_50_and_100_rows(): void
    {
        $config = require base_path('config/dynamic-reports.php');

        $this->assertSame([5, 10, 20, 50, 100], $config['preview_sizes']);
    }

    public function test_summary_grouping_uses_raw_trusted_semantic_expressions(): void
    {
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        $this->assertStringContainsString("groupByRaw((string) \$meta['expression'])", $compiler);
        $this->assertStringContainsString("orderByRaw((string) (\$metadata[\$id]['expression']", $compiler);
        $this->assertStringContainsString("orderByRaw((string) \$meta['expression'].", $compiler);
    }

    public function test_preview_database_failures_are_returned_as_json_with_reference(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        $this->assertStringContainsString('catch (QueryException $exception)', $controller);
        $this->assertStringContainsString("'error_reference' => \$reference", $controller);
        $this->assertStringContainsString("'error_type' => 'database_query'", $controller);
        $this->assertStringContainsString('Dynamic Query preview database failure.', $controller);
        $this->assertStringContainsString('data.error_reference', $view);
    }
}
