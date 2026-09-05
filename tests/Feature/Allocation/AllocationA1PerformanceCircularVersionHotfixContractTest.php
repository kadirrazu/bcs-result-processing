<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA1PerformanceCircularVersionHotfixContractTest extends TestCase
{
    #[Test]
    public function allocation_landing_is_lightweight_and_seat_upload_uses_current_finalized_circular_only(): void
    {
        $root = base_path();
        $controller = file_get_contents($root.'/app/Http/Controllers/AllocationController.php');
        $readiness = file_get_contents($root.'/app/Services/Allocation/AllocationReadinessService.php');
        $seat = file_get_contents($root.'/app/Services/Allocation/AllocationSeatBreakupService.php');
        $view = file_get_contents($root.'/resources/views/allocation/index.blade.php');

        self::assertStringContainsString('inspectDashboard()', $controller);
        self::assertStringContainsString('function inspectDashboard()', $readiness);
        self::assertStringContainsString('storedFinalizedSummary()', $readiness);
        self::assertStringContainsString('Strict hash verification runs at the Allocation pre-run gate', $readiness);

        // Workbook metadata is informational; current finalized Circular remains authority.
        self::assertStringContainsString('allocation_circular_version', $seat);
        self::assertStringContainsString('allocation_circular_hash', $seat);
        self::assertStringNotContainsString("getCustomPropertyValue('allocation_circular_version')", $seat);
        self::assertStringNotContainsString("getCustomPropertyValue('allocation_circular_hash')", $seat);

        // Latest implementation resolves against the current Circular collection and
        // safely handles serials such as 13.10 that Excel may coerce to 13.1.
        self::assertStringContainsString('$this->serialEquivalent($sl, $row[\'sl\'])', $seat);
        self::assertStringContainsString("\$sameTotal = \$matches->where('total_post', \$total)->values();", $seat);
        self::assertStringContainsString('Expected code(s): {$allowedCodes}', $seat);
        self::assertStringContainsString('Uploaded total_post={$total}; expected total_post={$expectedRow[\'total_post\']}', $seat);

        self::assertStringContainsString('in_array($col, [4,5,6], true)', $seat);
        self::assertStringContainsString('blank cff/em/phc cells are treated as 0', $seat);
        self::assertStringContainsString('FINALIZED HASH ON FILE', $view);
    }
}
