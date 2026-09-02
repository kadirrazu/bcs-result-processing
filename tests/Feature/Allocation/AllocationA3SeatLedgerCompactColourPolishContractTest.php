<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

class AllocationA3SeatLedgerCompactColourPolishContractTest extends TestCase
{
    public function test_phase_one_seat_ledger_uses_compact_abbreviation_and_conditional_capacity_colours(): void
    {
        $view = file_get_contents(resource_path('views/allocation/run-show.blade.php'));

        $this->assertStringContainsString('Cadre Abbreviation', $view);
        $this->assertStringNotContainsString('Cadre / Sub-Cadre Name', $view);
        $this->assertStringContainsString('$abbreviationByCode->get((int) $ledger->cadre_code', $view);

        // Overall seat-health colour rules.
        $this->assertStringContainsString("? 'text-success' : 'text-warning'", $view);
        $this->assertStringContainsString("? 'text-body' : 'text-danger'", $view);

        // MQ/CFF/EM/PHC use the identical health rules and left-aligned three-line values.
        $this->assertStringContainsString('a3-bucket-cell', $view);
        $this->assertStringContainsString("? 'text-success' : 'text-warning'", $view);
        $this->assertStringContainsString("? 'text-body' : 'text-danger'", $view);
        $this->assertStringContainsString('text-align: left; vertical-align: middle;', $view);
    }
}
