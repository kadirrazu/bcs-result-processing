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
        $landing = file_get_contents(resource_path('views/allocation/index.blade.php'));

        foreach (['allocation_a4_runs','allocation_a4_results','allocation_a4_seat_ledgers','allocation_a4_movement_events'] as $table) {
            $this->assertStringContainsString($table, $migration);
        }
        $this->assertStringNotContainsString("AllocationResult::query()->where('allocation_run_id'", $service);
        $this->assertStringNotContainsString("AllocationSeatLedger::query()->where('allocation_run_id'", $service);

        $this->assertStringContainsString("\$q['choice_position'] < (int) \$current['choice_position']", $service);
        $this->assertStringContainsString('QUOTA_TO_MERIT_CONVERSION', $service);
        $this->assertStringContainsString('INVARIANT_A4_DOWNWARD_MOVEMENT_FAILED', $service);
        $this->assertStringContainsString('INVARIANT_HIGHER_CHOICE_ATTAINABLE_FAILED', $service);
        $this->assertStringContainsString('INVARIANT_A4_MERIT_BYPASS_FAILED', $service);
        foreach (['quota_priority','eligible_CFF','eligible_EM','eligible_PHC','eligible_cff','eligible_em','eligible_phc'] as $needle) {
            $this->assertStringNotContainsString($needle, $service);
        }

        $this->assertStringContainsString('A4 Seat Ledger', $view);
        $this->assertStringContainsString('<th>NM</th><th>SHIFTED</th>', $view);
        $this->assertStringContainsString('View Original A3', $view);
        $this->assertStringContainsString("'Start Phase-2'", $landing);
        $this->assertStringContainsString('a4-landing-progress-wrap', $landing);
        $this->assertStringContainsString('pollA4()', $landing);
    }
}
