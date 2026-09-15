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

        // A6 keeps the lightweight A4/A5 source gate and adds a cheap metadata fail-safe
        // so stale Merit/A2 cannot leave historical Allocation publishable.
        self::assertStringContainsString('private function requireCurrentPublishingSource()', $readiness);
        self::assertStringContainsString('private function resolveCurrentSource()', $readiness);
        self::assertStringContainsString('public function requireReady()', $readiness);
        self::assertStringContainsString('public function requireReadyStrict()', $readiness);
        self::assertSame(2, substr_count($readiness, 'return $this->requireCurrentPublishingSource();'));

        // Cheap stored metadata is allowed; expensive full-chain dataset re-hashing must not return.
        self::assertStringContainsString('inspectDashboard()', $readiness);
        self::assertStringNotContainsString('inspectStrict()', $readiness);
        self::assertStringContainsString('Allocation publishing authority is not current:', $readiness);

        // Normal report pages and queued export entry points both use the source gate.
        self::assertGreaterThanOrEqual(4, substr_count($controller, '$a5 = $readiness->requireReady();'));
        self::assertGreaterThanOrEqual(3, substr_count($controller, '$a5 = $readiness->requireReadyStrict();'));

        // Worker performs a fast source/snapshot verification, not an A1-A5 full rehash.
        self::assertStringContainsString('VERIFYING_SOURCE', $worker);
        self::assertStringContainsString('assertFrozenSource', $worker);
    }
}
