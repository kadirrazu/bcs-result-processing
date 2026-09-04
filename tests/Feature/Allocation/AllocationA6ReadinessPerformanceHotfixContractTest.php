<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA6ReadinessPerformanceHotfixContractTest extends TestCase
{
    #[Test]
    public function normal_a6_page_readiness_is_lightweight_but_exports_keep_strict_gate(): void
    {
        $readiness = file_get_contents(app_path('Services/Allocation/AllocationA6ReadinessService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));

        $this->assertStringContainsString('inspectDashboard()', $readiness);
        $this->assertStringContainsString('public function requireReadyStrict()', $readiness);
        $this->assertStringContainsString('inspectStrict()', $readiness);

        $this->assertStringContainsString('$a5 = $readiness->requireReadyStrict();', $controller);
        $this->assertGreaterThanOrEqual(3, substr_count($controller, 'requireReadyStrict()'));
        $this->assertStringContainsString('$a5 = $readiness->requireReady();', $controller);
    }
}
