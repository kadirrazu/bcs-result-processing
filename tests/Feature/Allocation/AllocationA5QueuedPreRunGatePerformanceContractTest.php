<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA5QueuedPreRunGatePerformanceContractTest extends TestCase
{
    #[Test]
    public function a5_http_start_is_lightweight_and_strict_integrity_runs_inside_queue_worker(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA5.php'));

        $startOffset = strpos($controller, 'public function startA5(');
        $endOffset = strpos($controller, 'public function a5Status(', $startOffset);
        $start = substr($controller, $startOffset, $endOffset - $startOffset);

        self::assertStringContainsString('$readiness->inspectDashboard()', $start);
        self::assertStringNotContainsString('$readiness->inspectStrict()', $start);
        self::assertStringContainsString('ProcessAllocationA5::dispatch', $start);

        self::assertStringContainsString('AllocationReadinessService $readiness', $job);
        self::assertStringContainsString("'phase'=>'STRICT_PRE_RUN_GATE'", $job);
        self::assertStringContainsString('$gate = $readiness->inspectStrict();', $job);
        self::assertStringContainsString('$service->process($run', $job);

        self::assertLessThan(
            strpos($job, '$service->process($run'),
            strpos($job, '$gate = $readiness->inspectStrict();'),
            'Strict gate must execute before A5 business processing.'
        );
    }
}
