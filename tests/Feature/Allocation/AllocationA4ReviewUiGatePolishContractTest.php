<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA4ReviewUiGatePolishContractTest extends TestCase
{
    public function test_a4_review_is_split_gate_protected_and_circular_group_ordered(): void
    {
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/AllocationController.php'));
        $index = file_get_contents(resource_path('views/allocation/index.blade.php'));
        $ledger = file_get_contents(resource_path('views/allocation/a4-show.blade.php'));
        $candidates = file_get_contents(resource_path('views/allocation/a4-candidates.blade.php'));
        $cadre = file_get_contents(resource_path('views/allocation/a4-cadre-results.blade.php'));

        // Dedicated candidate and cadre drill-down routes keep the primary A4 page ledger-only.
        $this->assertStringContainsString("/a4/runs/{a4Run}/candidates", $routes);
        $this->assertStringContainsString("/a4/runs/{a4Run}/cadre/{circularEntry}", $routes);
        $this->assertStringContainsString('A4 Candidate Results', $candidates);
        $this->assertStringNotContainsString('<h3 class="card-title">A4 Candidate Results</h3>', $ledger);
        $this->assertStringContainsString("route('allocation.a4.candidates',\$a4Run)", $ledger);

        // Both UI and server-side processing gate A4 while any upstream readiness check is blocked.
        $this->assertStringContainsString('$readiness[\'ready\'] ? \'\' : \'disabled\'', $index);
        $this->assertStringContainsString('$readiness->inspectStrict()', $controller);
        $this->assertStringContainsString('Allocation Pre-run Gate is BLOCKED', $controller);

        // Circular group ordering must precede per-group serial ordering (GG then TT).
        $this->assertStringContainsString("'GG' => 0", $controller);
        $this->assertStringContainsString("'TT' => 1", $controller);
        $this->assertStringContainsString('General Cadre', $ledger);
        $this->assertStringContainsString('Technical / Professional Cadre', $ledger);

        // Ledger supports one combined code/abbreviation search plus exact cadre dropdown.
        $this->assertStringContainsString('name="ledger_search"', $ledger);
        $this->assertStringContainsString('name="ledger_cadre_code"', $ledger);
        $this->assertStringContainsString("route('allocation.a4.cadre-results',[\$a4Run,\$entry])", $ledger);
        $this->assertStringContainsString('Filtered candidates:', $cadre);

        // Recent A4 movement review shows operator-facing identities, not internal registration IDs.
        $this->assertStringContainsString('<th>Operator</th><th>Reg</th>', $candidates);
        $this->assertStringNotContainsString('<th>Actor</th><th>Reg ID</th>', $candidates);
        $this->assertStringContainsString('$movementRegistrationNumbers->get((int) $event->registration_id', $candidates);
        $this->assertStringContainsString('$event->actor_id }} - {{ $operator->name', $candidates);
        $this->assertStringContainsString('$abbreviationByCode->get((int) $event->from_cadre_code', $candidates);
        $this->assertStringContainsString('$abbreviationByCode->get((int) $event->to_cadre_code', $candidates);
        $this->assertStringContainsString("'movementRegistrationNumbers', 'movementOperators'", $controller);
    }
}
