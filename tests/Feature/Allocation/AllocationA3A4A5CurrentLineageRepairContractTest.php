<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA3A4A5CurrentLineageRepairContractTest extends TestCase
{
    #[Test]
    public function landing_reconciliation_does_not_stale_a5_bound_to_current_a4_of_current_a3(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationRunStaleService.php'));
        $a3Job = file_get_contents(app_path('Jobs/ProcessAllocationPhaseOne.php'));
        $a5Job = file_get_contents(app_path('Jobs/ProcessAllocationA5.php'));
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        self::assertStringContainsString('function supersedeEarlierA3ForNewA3', $service);
        self::assertStringContainsString('ALLOCATION_A3_SUPERSEDED_BY_A3_RERUN', $service);

        // Core bug fix: A5 is excluded from staling when its A4 belongs to the
        // exact current A3 lineage.
        self::assertStringContainsString('$validA4Ids = AllocationA4Run::query()', $service);
        self::assertStringContainsString("->where('phase1_run_id', (int) \$currentA3->id)", $service);
        self::assertStringContainsString("->whereNotIn('allocation_a4_run_id', \$validA4Ids)", $service);

        // A3 completion establishes one current A3 authority.
        self::assertStringContainsString(
            '$stale->supersedeEarlierA3ForNewA3($completed, $this->actorId)',
            $a3Job
        );

        // A5 re-run establishes one current A5 authority.
        self::assertStringContainsString('AllocationRunStaleService $stale', $a5Job);
        self::assertStringContainsString(
            '$stale->supersedeEarlierA5ForNewA5($completed, $this->actorId)',
            $a5Job
        );

        // Historical false-positive A5 created by the old reconciliation bug can
        // be repaired, but only when exact current A4 id/hash still match.
        self::assertStringContainsString('function repairFalsePositiveA5ForCurrentA4', $service);
        self::assertStringContainsString('ALLOCATION_A5_FALSE_STALE_REPAIRED', $service);
        self::assertStringContainsString("hash_equals((string) \$latest->a4_output_hash, (string) \$currentA4->a4_output_hash)", $service);
        self::assertStringContainsString('$this->repairFalsePositiveA5ForCurrentA4($currentA4);', $service);

        self::assertStringContainsString('SUPERSEDED', $view);
        self::assertStringContainsString("historical/superseded", $view);
    }
}
