<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocationLandingUiSummaryPolishContractTest extends TestCase
{
    #[Test]
    public function landing_has_operator_processing_summary_below_readiness(): void
    {
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        $this->assertStringContainsString('Allocation Processing Summary', $view);
        $this->assertStringContainsString('Seat Breakup</div><div class="summary-detail"', $view);
        $this->assertStringContainsString('A1 — Allocation Settings</div><div class="summary-detail"', $view);
        $this->assertStringContainsString('A2 — Frozen Input</div><div class="summary-detail"', $view);
        $this->assertStringContainsString('A3 — Phase-1</div><div class="summary-detail"', $view);
        $this->assertStringContainsString('A4 — Phase-2</div><div class="summary-detail"', $view);
        $this->assertStringNotContainsString('<h3 class="card-title">Processing State</h3>', $view);
    }

    #[Test]
    public function a1_settings_and_requested_table_columns_use_polished_alignment_contract(): void
    {
        $view = file_get_contents(resource_path('views/allocation/index.blade.php'));

        $this->assertStringContainsString('Configuration Status', $view);
        $this->assertStringContainsString('allocation-setting-label">Quota Priority', $view);
        $this->assertStringContainsString('allocation-setting-label">Provisional Target', $view);
        $this->assertStringContainsString('allocation-setting-label">Quota Breakup Minimum Total Post', $view);
        $this->assertStringContainsString('allocation-setting-value', $view);
        $this->assertStringContainsString('#allocation-seat-breakup-card tbody td:nth-child(8)', $view);
        $this->assertStringContainsString('#allocation-input-freeze-card tbody td:nth-child(6)', $view);
        $this->assertStringContainsString('#allocation-phase1-card tbody td:nth-child(9)', $view);
        $this->assertStringContainsString('#allocation-a4-card tbody td:nth-child(9)', $view);
    }
}
