<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA6ReadinessPerformanceHotfixContractTest extends TestCase
{
    #[Test]
    public function normal_pages_and_exports_use_the_latest_lightweight_publishing_source_gate(): void
    {
        $readiness = file_get_contents(app_path('Services/Allocation/AllocationA6ReadinessService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $worker = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));

        // A6 trusts finalized A5 assurance and checks only current A5/A4 lineage.
        self::assertStringContainsString('private function requireCurrentPublishingSource()', $readiness);
        self::assertStringContainsString('private function resolveCurrentSource()', $readiness);
        self::assertStringContainsString('public function requireReady()', $readiness);
        self::assertStringContainsString('public function requireReadyStrict()', $readiness);
        self::assertSame(2, substr_count($readiness, 'return $this->requireCurrentPublishingSource();'));

        // Removed pre-optimization full-chain rehash entry points must not return.
        self::assertStringNotContainsString('inspectDashboard()', $readiness);
        self::assertStringNotContainsString('inspectStrict()', $readiness);

        // Normal report pages and queued export entry points both use the source gate.
        self::assertGreaterThanOrEqual(4, substr_count($controller, '$a5 = $readiness->requireReady();'));
        self::assertGreaterThanOrEqual(3, substr_count($controller, '$a5 = $readiness->requireReadyStrict();'));

        // Worker performs a fast source/snapshot verification, not an A1-A5 full rehash.
        self::assertStringContainsString('VERIFYING_SOURCE', $worker);
        self::assertStringContainsString('assertFrozenSource', $worker);
    }
}
