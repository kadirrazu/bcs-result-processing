<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocationLandingActionAndA3DrilldownUiPolishContractTest extends TestCase
{
    #[Test]
    public function landing_phase_actions_are_compact_and_correctly_named(): void
    {
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        $this->assertStringContainsString('btn btn-sm btn-primary text-nowrap', $view);
        $this->assertStringContainsString('View Phase-1 Result', $view);
        $this->assertStringContainsString('View Phase-2 Result', $view);
        $this->assertStringNotContainsString('>View A3 Result</a>', $view);
        $this->assertStringContainsString('Re-run Phase-1', $view);
        $this->assertStringContainsString('Re-run Phase-2', $view);
    }

    #[Test]
    public function a3_ledger_has_cadre_drilldown_and_compact_header_actions(): void
    {
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $view = file_get_contents(resource_path('views/allocation/run-show.blade.php'));

        $this->assertStringContainsString("name('runs.cadre-results')", $routes);
        $this->assertStringContainsString('allocation.runs.cadre-results', $view);
        $this->assertStringContainsString('A3 Phase-1 Candidate Result', $view);
        $this->assertStringContainsString('A4 Phase-2 Candidate Result', $view);
        $this->assertStringContainsString('Back to Allocation', $view);
        $this->assertStringContainsString('btn btn-sm', $view);
    }
}
