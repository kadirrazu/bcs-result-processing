<?php

namespace Tests\Feature\NonCadre;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NonCadreAllocationNc4QueueContractTest extends TestCase
{
    #[Test]
    public function nc4_heavy_stages_are_dispatched_to_queue_and_progress_is_pollable(): void
    {
        $controller=file_get_contents(app_path('Http/Controllers/NonCadre/Allocation/NonCadreAllocationController.php'));
        $job=file_get_contents(app_path('Jobs/ProcessNonCadreAllocationStage.php'));
        $routes=file_get_contents(base_path('routes/non-cadre.php'));
        $service=file_get_contents(app_path('Services/NonCadre/Allocation/NonCadreAllocationService.php'));

        $this->assertStringContainsString('ProcessNonCadreAllocationStage::dispatch', $controller);
        $this->assertStringContainsString('implements ShouldQueue', $job);
        $this->assertStringContainsString("'INPUT_FREEZE'", $controller);
        $this->assertStringContainsString("'PHASE1'", $controller);
        $this->assertStringContainsString("'PHASE2'", $controller);
        $this->assertStringContainsString("'VALIDATION'", $controller);
        $this->assertStringContainsString("'RECOMPUTE'", $controller);
        $this->assertStringContainsString("/run/{run}/progress", $routes);
        $this->assertStringContainsString("array_chunk($r,500)", $service);
        $this->assertStringContainsString("'movement_type'", $service);
    }
}
