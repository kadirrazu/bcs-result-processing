<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA5MeritOrderHeaderPolishContractTest extends TestCase
{
    public function test_a5_candidate_lists_use_a4_merit_order_and_summary_actions_are_compact(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $view = file_get_contents(resource_path('views/allocation/a5-show.blade.php'));

        self::assertNotFalse($controller);
        self::assertNotFalse($view);

        // Both the all-candidate report and the cadre drill-down must source
        // display order from the persisted A4 merit position, not REG/cadre code.
        self::assertGreaterThanOrEqual(2, substr_count($controller, "->select('merit_position')"));
        self::assertGreaterThanOrEqual(2, substr_count($controller, "allocation_a5_candidate_results.allocation_a4_result_id"));
        self::assertStringNotContainsString("\$results = \$query->orderBy('cadre_code')->orderBy('reg')", $controller);
        self::assertStringNotContainsString("\$results = \$query->orderBy('reg')->paginate(100)", $controller);

        // Header controls stay compact and right-aligned alongside the title.
        self::assertStringContainsString('a5-header-actions', $view);
        self::assertStringContainsString('flex-wrap:nowrap', $view);
        self::assertStringContainsString('white-space:nowrap', $view);
        self::assertStringContainsString('font-size:.78rem', $view);
    }
}
