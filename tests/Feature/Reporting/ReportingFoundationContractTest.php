<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class ReportingFoundationContractTest extends TestCase
{
    public function test_workspace_overview_and_reporting_hub_use_lightweight_existing_authority(): void
    {
        $navigation = file_get_contents(config_path('navigation.php'));
        $overview = file_get_contents(app_path('Services/Overview/ExaminationOverviewService.php'));
        $reporting = file_get_contents(resource_path('views/reporting/index.blade.php'));

        self::assertStringContainsString("'route' => 'examination-overview.index'", $navigation);
        self::assertStringContainsString("'route' => 'examination-reports.index'", $navigation);
        self::assertStringContainsString('AllocationA6ReadinessService', $overview);
        self::assertStringContainsString("Registration::query()", $overview);
        self::assertSame(1, substr_count($overview, 'Registration::query()'));
        self::assertStringNotContainsString('->get()', $overview);
        foreach ([
            'Quota Total', 'CFF', 'EM', 'PHC',
            'Appeared', 'Absent', 'Passed', 'Pass %',
            'Registration Choice Count', 'Registration Intact', 'OMR Overridden', 'Optimized', 'Unchanged', 'Choice Empty After Optimization',
            'Allocated', 'Withheld', 'Cancelled', 'Final Publishing Ready Allocated', 'Quota Allocation Total', 'Remain Post', 'A5 Status',
        ] as $stat) {
            self::assertStringContainsString($stat, $overview);
        }
        self::assertStringContainsString("has_ff_quota = 2", $overview);
        self::assertStringContainsString("capacityResults()->sum('remaining_posts')", $overview);
        self::assertStringContainsString('AllocationResultDispositionState', $overview);
        self::assertStringContainsString('VivaResult::query()', $overview);

        self::assertStringContainsString('A6 - Allocation Reporting &amp; Export', $reporting);
        self::assertStringContainsString('Cadre Section Reporting', $reporting);
        self::assertStringContainsString('Research &amp; Statistics Section Reporting', $reporting);
    }

    public function test_completed_exam_guard_allows_read_and_reporting_but_blocks_processing_mutations(): void
    {
        $guard = file_get_contents(app_path('Http/Middleware/EnsureExaminationProcessingOpen.php'));
        $web = file_get_contents(base_path('routes/web.php'));

        self::assertStringContainsString('isMethodSafe()', $guard);
        self::assertStringContainsString("str_starts_with(\$routeName, 'allocation.a6.')", $guard);
        self::assertStringContainsString("\$examination?->is_completed", $guard);
        self::assertStringContainsString('EnsureExaminationProcessingOpen::class', $web);
    }
}
