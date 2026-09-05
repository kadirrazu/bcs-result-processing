<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA3A4UiConsistencyPolishContractTest extends TestCase
{
    public function test_landing_and_a3_a4_reviews_share_the_latest_ui_contract(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $index = file_get_contents(resource_path('views/allocation/index.blade.php'));
        $a3 = file_get_contents(resource_path('views/allocation/run-show.blade.php'));
        $a4 = file_get_contents(resource_path('views/allocation/a4-show.blade.php'));

        $seat = strpos($index, 'id="allocation-seat-breakup-card"');
        $a1 = strpos($index, 'id="allocation-settings-card"');
        $a2 = strpos($index, 'id="allocation-input-freeze-card"');
        $a3Card = strpos($index, 'id="allocation-phase1-card"');
        $a4Card = strpos($index, 'id="allocation-a4-card"');
        $this->assertTrue($seat < $a1 && $a1 < $a2 && $a2 < $a3Card && $a3Card < $a4Card);
        $this->assertStringContainsString('A1 — Allocation Settings', $index);
        $this->assertStringContainsString('A4 — Phase-2 NM + Shifting', $index);
        $this->assertStringContainsString('View Phase-1 Result', $index);
        $this->assertStringContainsString('View Phase-2 Result', $index);
        $this->assertStringContainsString('btn btn-sm btn-success', $index);

        foreach ([$a3, $a4] as $view) {
            $this->assertStringContainsString('name="ledger_search"', $view);
            $this->assertStringContainsString('name="ledger_cadre_code"', $view);
            $this->assertStringContainsString('Cadre Code / Cadre Abbreviation', $view);
            $this->assertStringContainsString('Cadre Filter', $view);
            $this->assertStringContainsString(' - {{ ', $view);
        }
        $this->assertStringContainsString('$ledgerSearch', $controller);
        $this->assertStringContainsString('$ledgerCadreCode', $controller);

        $this->assertStringContainsString('Allocated', $a4);
        $this->assertStringContainsString('Basis', $a4);
        $this->assertStringContainsString('Movement', $a4);
        $this->assertStringContainsString('Fixed Point', $a4);
        $this->assertStringContainsString('quota_to_merit_count', $a4);

        $this->assertStringContainsString('<th class="text-start">Cadre</th>', $a3);
        $this->assertStringContainsString('<th class="text-start">Cadre</th>', $a4);
        $this->assertStringNotContainsString('<th>Cadre Abbreviation</th>\n            <th>Cadre Code</th>', $a3);
    }
}
