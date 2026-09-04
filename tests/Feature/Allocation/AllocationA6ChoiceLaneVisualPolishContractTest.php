<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

class AllocationA6ChoiceLaneVisualPolishContractTest extends TestCase
{
    public function test_candidate_detail_choice_lanes_are_full_width_compact_and_highlight_only_effective_allocation(): void
    {
        $view = file_get_contents(resource_path('views/allocation/a6/candidate-show.blade.php'));

        $this->assertStringContainsString('grid-template-columns:repeat(20,minmax(0,1fr))', $view);
        $this->assertStringContainsString('a6-choice-title', $view);
        $this->assertStringContainsString('a6-choice-lane', $view);
        $this->assertStringContainsString("$key === 'effective' && $allocation", $view);
        $this->assertStringContainsString('a6-choice-chip-allocated', $view);
        $this->assertStringContainsString('(int)$allocation->cadre_code', $view);
    }
}
