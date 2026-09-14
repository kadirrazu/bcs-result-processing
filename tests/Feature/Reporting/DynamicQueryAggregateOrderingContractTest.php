<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryAggregateOrderingContractTest extends TestCase
{
    public function test_summary_aggregate_can_define_ascending_or_descending_order(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/DynamicQueryBuilderController.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        $this->assertStringContainsString("aggregates.*.sort_direction", $controller);
        $this->assertStringContainsString("'nullable', 'in:asc,desc'", $controller);

        $this->assertStringContainsString("\$aggregate['sort_direction']", $compiler);
        $this->assertStringContainsString("orderByRaw('`a'.\$index.'` '.strtoupper(\$direction))", $compiler);

        $this->assertStringContainsString('class="form-select form-select-sm dq-a-sort"', $view);
        $this->assertStringContainsString('<option value="desc">Descending</option>', $view);
        $this->assertStringContainsString("sort_direction:r.querySelector('.dq-a-sort').value", $view);
        $this->assertStringContainsString("sort.value=initial.sort_direction||''", $view);
    }

    public function test_aggregate_order_is_preserved_in_saved_definition_and_export_path(): void
    {
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $saved = file_get_contents(app_path('Services/Reporting/DynamicQuery/SavedDynamicReportService.php'));

        $this->assertGreaterThanOrEqual(2, substr_count($compiler, "sort_direction"));
        $this->assertStringContainsString("foreach(\$aggregates as \$index=>\$aggregate)", $compiler);
        $this->assertStringContainsString("\$aggregate['sort_direction']", $saved);
        $this->assertStringContainsString("'sort_direction' => \$sortDirection", $saved);
        $this->assertStringContainsString("['asc', 'desc']", $saved);
    }
}
