<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

class AllocationA6CadreTotalsUiPolishContractTest extends TestCase
{
    public function test_a6_cadre_reporting_views_show_requested_totals_and_labels(): void
    {
        $cadresView = file_get_contents(resource_path('views/allocation/a6/cadres.blade.php'));
        $cadreShowView = file_get_contents(resource_path('views/allocation/a6/cadre-show.blade.php'));

        $this->assertStringContainsString('Total Post', $cadresView);
        $this->assertStringNotContainsString('>Sanctioned<', $cadresView);
        $this->assertStringContainsString("\$cadres->sum", $cadresView);
        $this->assertStringContainsString('A5 Allocated:', $cadreShowView);
        $this->assertStringContainsString('Withheld:', $cadreShowView);
        $this->assertStringContainsString('Cancelled:', $cadreShowView);
        $this->assertStringContainsString('Published Active:', $cadreShowView);
        $this->assertStringContainsString("\$cadre['allocated']", $cadreShowView);
        $this->assertStringContainsString("\$cadre['published']", $cadreShowView);
        $this->assertStringContainsString('ACTIVE / Publishable', $cadreShowView);
    }
}
