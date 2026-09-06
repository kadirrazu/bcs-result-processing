<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA4SingleCurrentAuthorityContractTest extends TestCase
{
    #[Test]
    public function successful_a4_rerun_supersedes_older_completed_a4_before_a5_lineage_is_evaluated(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationRunStaleService.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA4.php'));

        self::assertStringContainsString('function supersedeEarlierA4ForNewA4', $service);
        self::assertStringContainsString("->where('status', 'a4_complete')", $service);
        self::assertStringContainsString("->where('is_stale', false)", $service);
        self::assertStringContainsString('->whereKeyNot((int) $currentA4->id)', $service);
        self::assertStringContainsString("'event' => 'ALLOCATION_A4_SUPERSEDED_BY_A4_RERUN'", $service);
        self::assertStringContainsString('historical/superseded', $service);

        $supersedePos = strpos($job, '$stale->supersedeEarlierA4ForNewA4($completed, $this->actorId)');
        $a5Pos = strpos($job, '$stale->staleA5ForNewA4($completed, $this->actorId)');

        self::assertNotFalse($supersedePos);
        self::assertNotFalse($a5Pos);
        self::assertLessThan($a5Pos, $supersedePos);

        // Landing reconciliation also repairs older databases that already contain
        // more than one non-stale completed A4.
        self::assertStringContainsString('$this->supersedeEarlierA4ForNewA4($currentA4);', $service);
    }
}
