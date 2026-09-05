<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA5CadreGroupedMeritDisplayPolishContractTest extends TestCase
{
    public function test_all_candidate_report_groups_by_cadre_then_merit_and_both_reports_show_merit_position(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $candidateView = file_get_contents(resource_path('views/allocation/a5-candidates.blade.php'));
        $cadreView = file_get_contents(resource_path('views/allocation/a5-cadre-results.blade.php'));

        self::assertNotFalse($controller);
        self::assertNotFalse($candidateView);
        self::assertNotFalse($cadreView);

        // Full report: keep each cadre together, then show candidates in that
        // cadre's resolved A4 merit order. REG is deterministic fallback only.
        self::assertStringContainsString("->orderBy('cadre_code')", $controller);
        self::assertGreaterThanOrEqual(2, substr_count($controller, "->orderBy('merit_position')"));
        self::assertGreaterThanOrEqual(2, substr_count($controller, "->addSelect(['merit_position' => \$meritPositionSubquery])"));

        // Both operator-facing reports must make the resolved merit evidence visible.
        self::assertStringContainsString('<th>Merit Position</th>', $candidateView);
        self::assertStringContainsString('$row->merit_position', $candidateView);
        self::assertStringContainsString('<th>Merit Position</th>', $cadreView);
        self::assertStringContainsString('$row->merit_position', $cadreView);
    }
}
