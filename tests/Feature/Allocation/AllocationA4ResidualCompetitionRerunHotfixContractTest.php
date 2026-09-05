<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA4ResidualCompetitionRerunHotfixContractTest extends TestCase
{
    public function test_a4_re_solves_full_residual_merit_capacity_and_exposes_latest_rerun_control(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationA4Service.php'));
        $landing = file_get_contents(resource_path('views/allocation/index.blade.php'));
        $resultView = file_get_contents(resource_path('views/allocation/a4-show.blade.php'));

        $this->assertStringContainsString('FULL residual merit competition', $service);
        $this->assertStringContainsString('residualMeritCapacity', $service);
        $this->assertStringContainsString('buildResidualPreferences', $service);
        $this->assertStringContainsString('stableResidualCompetition', $service);
        $this->assertStringNotContainsString('stableFillFreeCapacity', $service);
        $this->assertStringNotContainsString('freeMeritCapacity', $service);
        $this->assertStringContainsString('locked FINAL MQ occupancy', $service);
        $this->assertStringContainsString('TEMPORARY MQ holder must never lose the current fallback', $service);
        $this->assertStringContainsString('INVARIANT_A4_TEMPORARY_MQ_FALLBACK_LOST', $service);
        $this->assertStringContainsString('QUOTA_TO_MERIT_CONVERSION', $service);
        $this->assertStringNotContainsString('quota_priority', $service);

        // Latest UI centralizes rerun on the Allocation landing page; review stays read-only.
        $this->assertStringContainsString("'Re-run Phase-2' : 'Start Phase-2'", $landing);
        $this->assertStringContainsString("route('allocation.a4.start'", $landing);
        $this->assertStringContainsString('View Original A3', $resultView);
        $this->assertStringContainsString('Back to Allocation', $resultView);
    }
}
