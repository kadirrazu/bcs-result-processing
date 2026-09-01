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

        // Metadata may be written for information/audit, but must never decide upload validity.
        self::assertStringContainsString('allocation_circular_version', $seat);
        self::assertStringContainsString('allocation_circular_hash', $seat);
        self::assertStringNotContainsString("getCustomPropertyValue('allocation_circular_version')", $seat);
        self::assertStringNotContainsString("getCustomPropertyValue('allocation_circular_hash')", $seat);
        self::assertStringNotContainsString('This Seat Breakup Excel was generated from Circular', $seat);
        self::assertStringNotContainsString('belongs to an older/different finalized Circular dataset', $seat);

        // Circular serial is display ordering and may repeat across Circular sections.
        // The copied pair (sl, cadre_code) identifies the exact current Circular row.
        self::assertStringContainsString("return \$sl.'|'.\$cadreCode;", $seat);
        self::assertStringContainsString('$key = $this->rowKey($sl, $code);', $seat);
        self::assertStringContainsString('Expected code(s): {$allowedCodes}', $seat);
        self::assertStringContainsString('Uploaded total_post={$total}; expected total_post={$expected[$key][\'total_post\']}', $seat);

        // Operators commonly leave zero quota buckets blank in Excel; those blanks
        // are semantically zero, while cadre_code/total_post/MQ remain explicit.
        self::assertStringContainsString("in_array(\$col, [4,5,6], true)", $seat);
        self::assertStringContainsString('blank cff/em/phc cells are treated as 0', $seat);
        self::assertStringContainsString('FINALIZED HASH ON FILE', $view);
    }
}
