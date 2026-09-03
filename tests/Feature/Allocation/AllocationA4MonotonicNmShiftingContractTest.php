<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA4MonotonicNmShiftingContractTest extends TestCase
{
    #[Test]
    public function a4_is_separate_monotonic_merit_only_processing_with_audit(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationA4Service.php'));
        $migration = file_get_contents(database_path('examination-migrations/2026_09_03_080000_create_allocation_a4_monotonic_nm_shifting.php'));
        $view = file_get_contents(resource_path('views/allocation/a4-show.blade.php'));
        $runView = file_get_contents(resource_path('views/allocation/run-show.blade.php'));

        // A4 must be a separate persisted layer and must never overwrite A3 evidence.
        $this->assertStringContainsString("allocation_a4_runs", $migration);
        $this->assertStringContainsString("allocation_a4_results", $migration);
        $this->assertStringContainsString("allocation_a4_seat_ledgers", $migration);
        $this->assertStringContainsString("allocation_a4_movement_events", $migration);
        $this->assertStringNotContainsString("AllocationResult::query()->where('allocation_run_id'", $service);
        $this->assertStringNotContainsString("AllocationSeatLedger::query()->where('allocation_run_id'", $service);

        // Locked A4 philosophy: higher choices only + same-cadre quota normalization.
        $this->assertStringContainsString('choice_position\'] < (int) $current[\'choice_position\']', $service);
        $this->assertStringContainsString('QUOTA_TO_MERIT_CONVERSION', $service);
        $this->assertStringContainsString('INVARIANT_A4_DOWNWARD_MOVEMENT_FAILED', $service);
        $this->assertStringContainsString('INVARIANT_HIGHER_CHOICE_ATTAINABLE_FAILED', $service);
        $this->assertStringContainsString('INVARIANT_A4_MERIT_BYPASS_FAILED', $service);

        // A4 winner selection must not consult quota priority or entitlement.
        $this->assertStringNotContainsString('quota_priority', $service);
        $this->assertStringNotContainsString('eligible_CFF', $service);
        $this->assertStringNotContainsString('eligible_EM', $service);
        $this->assertStringNotContainsString('eligible_PHC', $service);
        $this->assertStringNotContainsString('eligible_cff', $service);
        $this->assertStringNotContainsString('eligible_em', $service);
        $this->assertStringNotContainsString('eligible_phc', $service);

        // UI remains A3-like while making NM/SHIFTED and original A3 evidence explicit.
        $this->assertStringContainsString('A4 Seat Ledger', $view);
        $this->assertStringContainsString('<th>NM</th><th>SHIFTED</th>', $view);
        $this->assertStringContainsString('Recent A4 Movement Audit', $view);
        $this->assertStringContainsString('View Original A3', $view);
        $this->assertStringContainsString('Start A4 NM + Shifting', $runView);
        $this->assertStringContainsString('fetch(url', $runView);
    }
}
