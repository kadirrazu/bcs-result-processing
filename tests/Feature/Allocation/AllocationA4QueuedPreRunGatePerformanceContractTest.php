<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA4QueuedPreRunGatePerformanceContractTest extends TestCase
{
    #[Test]
    public function a4_http_start_is_lightweight_and_strict_integrity_runs_inside_queue_worker(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA4.php'));

        $start = substr(
            $controller,
            strpos($controller, 'public function startA4('),
            strpos($controller, '/** JSON polling endpoint for A4 progress. */')
                - strpos($controller, 'public function startA4(')
        );

        self::assertStringContainsString('$readiness->inspectDashboard()', $start);
        self::assertStringNotContainsString('$readiness->inspectStrict()', $start);
        self::assertStringContainsString('ProcessAllocationA4::dispatch', $start);

        self::assertStringContainsString('AllocationReadinessService $readiness', $job);
        self::assertStringContainsString("'phase' => 'STRICT_PRE_RUN_GATE'", $job);
        self::assertStringContainsString('$gate = $readiness->inspectStrict();', $job);
        self::assertStringContainsString('$service->process($run', $job);

        self::assertLessThan(
            strpos($job, '$service->process($run'),
            strpos($job, '$gate = $readiness->inspectStrict();'),
            'Strict gate must execute before A4 business processing.'
        );
    }
}
