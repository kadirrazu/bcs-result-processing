<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

final class AllocationA3RunReviewUiPolishContractTest extends TestCase
{
    public function test_phase_one_seat_ledger_uses_circular_serial_names_and_explicit_capacity_breakdown(): void
    {
        $view = file_get_contents(resource_path('views/allocation/run-show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));

        $this->assertStringContainsString('Cadre / Sub-Cadre Name', $view);
        $this->assertStringContainsString('Total Post', $view);
        $this->assertStringContainsString('Allocated Post', $view);
        $this->assertStringContainsString('Remain Post', $view);
        $this->assertStringContainsString('Total: {{ number_format($bucket[\'total\']) }}', $view);
        $this->assertStringContainsString('Allocated: {{ number_format($bucket[\'allocated\']) }}', $view);
        $this->assertStringContainsString('Remain: {{ number_format($bucket[\'remain\']) }}', $view);
        $this->assertStringContainsString("text-success", $view);
        $this->assertStringContainsString("text-danger", $view);
        $this->assertStringContainsString('authoritative Circular serial order', $controller);
        $this->assertStringContainsString('$a?->cadre_serial', $controller);
        $this->assertStringContainsString('$a?->sub_serial', $controller);
    }

    public function test_candidate_results_use_dropdown_filters_status_and_abbreviation_column(): void
    {
        $view = file_get_contents(resource_path('views/allocation/run-show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));

        $this->assertStringContainsString('<select class="form-select" name="cadre_code">', $view);
        $this->assertStringContainsString('name="decision_status"', $view);
        $this->assertStringContainsString('Cadre Abbreviation', $view);
        $this->assertStringContainsString('$abbreviationByCode->get', $view);
        $this->assertStringContainsString("['', 'FINAL', 'TEMPORARY']", $controller);
        $this->assertStringContainsString("where('decision_status', \$decisionStatus)", $controller);
        $this->assertStringContainsString('CadreMaster::query()', $controller);
        $this->assertStringContainsString('CadreSubMaster::query()', $controller);
    }

    public function test_review_tables_have_bold_headers_and_required_alignment_contract(): void
    {
        $view = file_get_contents(resource_path('views/allocation/run-show.blade.php'));

        $this->assertStringContainsString('font-weight: 700', $view);
        $this->assertStringContainsString('.a3-seat-ledger th:not(.a3-name-col)', $view);
        $this->assertStringContainsString('.a3-results-table th,', $view);
        $this->assertStringContainsString('text-align: center; vertical-align: middle;', $view);
        $this->assertStringContainsString('.a3-seat-ledger .a3-name-col { text-align: left;', $view);
    }
}
