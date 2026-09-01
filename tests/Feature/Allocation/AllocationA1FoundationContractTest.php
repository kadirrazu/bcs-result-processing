<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA1FoundationContractTest extends TestCase
{
    #[Test]
    public function allocation_a1_foundation_contract_is_present(): void
    {
        $root = base_path();
        $migration = file_get_contents($root.'/database/examination-migrations/2026_09_01_210000_create_allocation_foundation.php');
        $service = file_get_contents($root.'/app/Services/Allocation/AllocationSeatBreakupService.php');
        $readiness = file_get_contents($root.'/app/Services/Allocation/AllocationReadinessService.php');
        $view = file_get_contents($root.'/resources/views/allocation/index.blade.php');
        $routes = file_get_contents($root.'/routes/allocation.php');

        foreach (['allocation_settings','allocation_seat_breakup_versions','allocation_seat_breakup_rows','allocation_processing_states','allocation_processing_audits'] as $table) {
            self::assertStringContainsString("->create('{$table}'", $migration);
        }

        self::assertStringContainsString("['sl', 'cadre_code', 'total_post', 'mq', 'cff', 'em', 'phc']", $service);
        self::assertStringContainsString('$total < 10', $service);
        self::assertStringContainsString('$mq !== $total || $cff !== 0 || $em !== 0 || $phc !== 0', $service);
        self::assertStringContainsString('SEAT_BREAKUP_HASH_MISMATCH', $service);
        self::assertStringContainsString('ALLOCATION_SETTINGS_HASH_MISMATCH', file_get_contents($root.'/app/Services/Allocation/AllocationSettingsService.php'));
        self::assertStringContainsString('Allocation Input Freeze', $readiness);
        self::assertStringContainsString("'ready' => false", $readiness);
        self::assertStringContainsString('Upstream Readiness & Integrity Board', $view);
        self::assertStringContainsString("name('allocation.')", $routes);
    }
}
