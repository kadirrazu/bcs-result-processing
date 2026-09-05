<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

final class AllocationA3RunReviewUiPolishContractTest extends TestCase
{
    public function test_phase_one_seat_ledger_uses_latest_compact_circular_group_layout(): void
    {
        $view = file_get_contents(resource_path('views/allocation/run-show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));

        $this->assertStringContainsString('Phase-1 Seat Ledger', $view);
        $this->assertStringContainsString('a3-group-row', $view);
        $this->assertStringContainsString('a3-column-header', $view);
        $this->assertStringContainsString('<th class="text-start">Cadre</th>', $view);
        $this->assertStringContainsString('Total Post', $view);
        $this->assertStringContainsString('Allocated Post', $view);
        $this->assertStringContainsString('Remain Post', $view);
        $this->assertStringContainsString("Total: {{ number_format(\$bucket['total']) }}", $view);
        $this->assertStringContainsString("Allocated: {{ number_format(\$bucket['allocated']) }}", $view);
        $this->assertStringContainsString("Remain: {{ number_format(\$bucket['remain']) }}", $view);
        $this->assertStringContainsString('cadre_serial', $controller);
        $this->assertStringContainsString('sub_serial', $controller);
    }

    public function test_candidate_results_use_separate_latest_candidate_review_page(): void
    {
        $view = file_get_contents(resource_path('views/allocation/run-candidates.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));

        $this->assertStringContainsString('name="cadre_code"', $view);
        $this->assertStringContainsString('name="decision_status"', $view);
        $this->assertStringContainsString('Cadre Abbreviation', $view);
        $this->assertStringContainsString('$abbreviationByCode->get', $view);
        $this->assertStringContainsString("['', 'FINAL', 'TEMPORARY']", $controller);
        $this->assertStringContainsString("where('decision_status', \$decisionStatus)", $controller);
        $this->assertStringContainsString('CadreMaster::query()', $controller);
        $this->assertStringContainsString('CadreSubMaster::query()', $controller);
    }

    public function test_review_tables_keep_latest_bold_and_centered_alignment_contract(): void
    {
        $ledger = file_get_contents(resource_path('views/allocation/run-show.blade.php'));
        $candidates = file_get_contents(resource_path('views/allocation/run-candidates.blade.php'));

        $this->assertStringContainsString('.a3-seat-ledger .a3-column-header th', $ledger);
        $this->assertStringContainsString('font-weight: 700', $ledger);
        $this->assertStringContainsString('.a3-seat-ledger th,', $ledger);
        $this->assertStringContainsString('text-align: center; vertical-align: middle;', $ledger);
        $this->assertStringContainsString('.a3-seat-ledger .a3-cadre-cell', $ledger);
        $this->assertStringContainsString('.a3-results-table thead th{font-weight:700}', $candidates);
        $this->assertStringContainsString('.a3-results-table th,.a3-results-table td{text-align:center;vertical-align:middle}', $candidates);
    }
}
