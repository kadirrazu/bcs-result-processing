<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocationPhaseStaleLineageContractTest extends TestCase
{
    #[Test]
    public function allocation_phase_runs_have_explicit_stale_metadata_and_propagation_contracts(): void
    {
        $migration = file_get_contents(base_path('database/examination-migrations/2026_09_04_100000_add_stale_metadata_to_allocation_phase_runs.php'));
        $service = file_get_contents(app_path('Services/Allocation/AllocationRunStaleService.php'));
        $freeze = file_get_contents(app_path('Services/Allocation/AllocationInputFreezeService.php'));
        $phase1 = file_get_contents(app_path('Services/Allocation/AllocationPhaseOneService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));

        $this->assertStringContainsString("'is_stale'", $migration);
        $this->assertStringContainsString('staleA3AndA4', $service);
        $this->assertStringContainsString('staleA4ForNewA3', $service);
        $this->assertStringContainsString('reconcileCurrentness', $service);
        $this->assertStringContainsString('A2 Allocation Input Freeze was re-built', $freeze);
        $this->assertStringContainsString('staleA4ForNewA3($completed', $phase1);
        $this->assertStringContainsString('current non-stale completed A3', $controller);
    }

    #[Test]
    public function landing_preserves_history_but_visibly_marks_stale_a3_and_a4(): void
    {
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        $this->assertStringContainsString('A3 STALE / OUTDATED', $view);
        $this->assertStringContainsString('A4 STALE / OUTDATED', $view);
        $this->assertStringContainsString('STALE / OUTDATED', $view);
        $this->assertStringContainsString("status === 'phase1_complete' && !(bool)\$r->is_stale", $view);
    }
}
