<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA4VisibilityImmutabilityHotfixContractTest extends TestCase
{
    #[Test]
    public function a4_reads_a3_through_relations_and_is_visible_from_allocation_landing(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationA4Service.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $index = file_get_contents(resource_path('views/allocation/index.blade.php'));

        $this->assertStringContainsString('$phase1->results()', $service);
        $this->assertStringContainsString('$phase1->seatLedgers()', $service);
        $this->assertStringNotContainsString("AllocationResult::query()->where('allocation_run_id'", $service);
        $this->assertStringNotContainsString("AllocationSeatLedger::query()->where('allocation_run_id'", $service);

        $this->assertStringContainsString("'a4Runs' => AllocationA4Run::query()->with('phase1Run')", $controller);
        $this->assertStringContainsString('A4 — Phase-2 NM + Shifting', $index);
        $this->assertStringContainsString("'Start Phase-2'", $index);
        $this->assertStringContainsString('View Phase-2 Result', $index);
        $this->assertStringContainsString('<th>NM</th><th>SHIFTED</th>', $index);
    }
}
