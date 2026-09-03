<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocationA3A4ReviewSeparationAndGroupHeadingPolishContractTest extends TestCase
{
    #[Test]
    public function a3_candidate_results_are_separate_and_ledger_groups_repeat_headings(): void
    {
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $a3 = file_get_contents(resource_path('views/allocation/run-show.blade.php'));
        $a3Candidates = file_get_contents(resource_path('views/allocation/run-candidates.blade.php'));
        $a4 = file_get_contents(resource_path('views/allocation/a4-show.blade.php'));

        $this->assertStringContainsString("name('runs.candidates')", $routes);
        $this->assertStringContainsString("allocation.runs.candidates", $a3);
        $this->assertStringNotContainsString('<h3 class="card-title">Phase-1 Candidate Results</h3>', $a3);
        $this->assertStringContainsString('Phase-1 Candidate Results', $a3Candidates);

        $this->assertStringContainsString('a3-group-row', $a3);
        $this->assertStringContainsString('a3-column-header', $a3);
        $this->assertStringContainsString('a4-group-row', $a4);
        $this->assertStringContainsString('a4-column-header', $a4);
        $this->assertStringContainsString('class="a3-cadre-cell"', $a3);
        $this->assertStringContainsString('class="a4-cadre-cell"', $a4);
    }

    #[Test]
    public function landing_preserves_section_actions_and_phase_run_log_labels(): void
    {
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        $this->assertStringContainsString('View A3 Result', $view);
        $this->assertStringContainsString('View Phase-1 Result', $view);
        $this->assertStringContainsString('View Phase-2 Result', $view);
        $this->assertStringContainsString('Re-run Phase-1 MQ + Quota', $view);
        $this->assertStringContainsString('Re-run A4 NM + Shifting', $view);
    }
}
