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
        $this->assertStringContainsString('Total Allocated:', $cadreShowView);
        $this->assertStringContainsString("\$results->total()", $cadreShowView);
    }
}
