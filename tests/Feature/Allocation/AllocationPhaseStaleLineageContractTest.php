<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocationPhaseStaleLineageContractTest extends TestCase
{
    #[Test]
    public function allocation_phase_runs_have_explicit_stale_metadata_and_currentness_reconciliation(): void
    {
        $migration = file_get_contents(base_path('database/examination-migrations/2026_09_04_100000_add_stale_metadata_to_allocation_phase_runs.php'));
        $service = file_get_contents(app_path('Services/Allocation/AllocationRunStaleService.php'));
        $freeze = file_get_contents(app_path('Services/Allocation/AllocationInputFreezeService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));

        self::assertStringContainsString("'is_stale'", $migration);
        self::assertStringContainsString('staleA3AndA4', $service);
        self::assertStringContainsString('staleA4ForNewA3', $service);
        self::assertStringContainsString('supersedeEarlierA3ForNewA3', $service);
        self::assertStringContainsString('ALLOCATION_A3_SUPERSEDED_BY_A3_RERUN', $service);
        self::assertStringContainsString('$validA4Ids', $service);
        self::assertStringContainsString("->whereNotIn('allocation_a4_run_id', \$validA4Ids)", $service);
        self::assertStringContainsString('supersedeEarlierA4ForNewA4', $service);
        self::assertStringContainsString('ALLOCATION_A4_SUPERSEDED_BY_A4_RERUN', $service);
        self::assertStringContainsString('reconcileCurrentness', $service);
        self::assertStringContainsString('staleA4ForNewA3($currentA3)', $service);
        self::assertStringContainsString("whereIn('status', ['validated_ok','validated_failed','finalized'])", $service);
        self::assertStringContainsString('Upstream A3/A4 Allocation result is STALE / OUTDATED', $service);

        $phaseOneJob = file_get_contents(app_path('Jobs/ProcessAllocationPhaseOne.php'));
        self::assertStringContainsString('AllocationRunStaleService $stale', $phaseOneJob);
        self::assertStringContainsString('supersedeEarlierA3ForNewA3($completed, $this->actorId)', $phaseOneJob);
        self::assertStringContainsString('staleA4ForNewA3($completed, $this->actorId)', $phaseOneJob);

        $a4Job = file_get_contents(app_path('Jobs/ProcessAllocationA4.php'));
        self::assertStringContainsString('supersedeEarlierA4ForNewA4($completed, $this->actorId)', $a4Job);
        self::assertStringContainsString('staleA5ForNewA4($completed, $this->actorId)', $a4Job);
        self::assertStringContainsString('A2 Allocation Input Freeze was re-built', $freeze);
        self::assertStringContainsString('reconcileCurrentness()', $controller);
        self::assertStringContainsString('current non-stale completed A3', $controller);
    }

    #[Test]
    public function landing_preserves_history_but_visibly_marks_stale_a3_and_a4(): void
    {
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        self::assertStringContainsString('A3 STALE / OUTDATED', $view);
        self::assertStringContainsString('A4 STALE / OUTDATED', $view);
        self::assertStringContainsString('STALE / OUTDATED', $view);
        self::assertStringContainsString("status === 'phase1_complete' && !(bool)\$r->is_stale", $view);
    }
}
