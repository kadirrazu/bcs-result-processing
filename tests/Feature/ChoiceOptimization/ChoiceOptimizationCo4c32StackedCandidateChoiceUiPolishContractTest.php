<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c32StackedCandidateChoiceUiPolishContractTest extends TestCase
{
    public function test_listing_uses_full_width_candidate_and_choice_rows_without_table_scroll(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/historical-choices-index.blade.php'));
        $partial = file_get_contents(resource_path('views/choice-optimization/partials/choice-code-lane.blade.php'));

        $this->assertStringNotContainsString('<div class="table-responsive">', $view);
        $this->assertStringContainsString('Current Candidate', $view);
        $this->assertStringContainsString('Previous BCS Match', $view);
        $this->assertStringContainsString('Input Effective Choice', $view);
        $this->assertStringContainsString('Allocation-ready Choice', $view);

        $this->assertStringContainsString('BCS {{ $rec[\'bcs_number\'] ?? \'—\' }} - {{ $rec[\'cadre\'] ?? \'—\' }}', $view);
        $this->assertStringContainsString('NO PREVIOUS BCS MATCH', $view);
        $this->assertStringContainsString('NO HISTORICAL DATA', $view);

        $this->assertStringContainsString('d-flex flex-wrap', $partial);
        $this->assertStringNotContainsString('overflow-auto', $partial);
        $this->assertStringContainsString('d-inline-flex flex-column', $partial);
        $this->assertStringContainsString('align-items-center', $partial);
    }

    public function test_detail_uses_same_wrapped_choice_lanes_and_explicit_optimization_reason(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/historical-choice-show.blade.php'));

        $this->assertStringContainsString('Choice Lineage', $view);
        $this->assertStringContainsString('Optimization Reason', $view);
        $this->assertStringContainsString('NO PREVIOUS BCS MATCH', $view);
        $this->assertStringContainsString('BCS {{ $match->previous_bcs_number }} - {{ $match->previous_cadre', $view);
        $this->assertStringContainsString('Applied cutoff:', $view);
    }
}
