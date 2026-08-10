<?php

namespace Tests\Feature;

use Tests\TestCase;

final class CircularLandingUiHomogeneityContractTest extends TestCase
{
    public function test_circular_landing_uses_homogeneous_status_board_and_separate_quick_actions(): void
    {
        $blade = file_get_contents(resource_path('views/circular/index.blade.php'));

        $this->assertStringContainsString('Quick Actions', $blade);
        $this->assertStringContainsString('Processing Status Board', $blade);
        $this->assertStringContainsString('card mb-3 shadow-sm', $blade);
        $this->assertStringContainsString('table table-vcenter card-table mb-0', $blade);
        $this->assertStringContainsString('Effective Dataset Approval', $blade);
        $this->assertStringContainsString('Authority Preview', $blade);
        $this->assertStringContainsString('Authority Confirmation', $blade);
        $this->assertStringContainsString('Circular Finalization', $blade);
        $this->assertStringContainsString('Circular Excel Import', $blade);
        $this->assertStringContainsString('Circular data authority', $blade);
    }
}
