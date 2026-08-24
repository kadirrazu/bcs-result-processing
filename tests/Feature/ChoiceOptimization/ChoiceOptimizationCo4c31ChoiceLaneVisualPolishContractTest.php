<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c31ChoiceLaneVisualPolishContractTest extends TestCase
{
    public function test_listing_stacks_input_and_allocation_ready_choices_with_code_abbreviation_and_horizontal_lane(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/historical-choices-index.blade.php'));
        $partial = file_get_contents(resource_path('views/choice-optimization/partials/choice-code-lane.blade.php'));

        $this->assertStringContainsString('choiceCodeAbbrMap', $controller);
        $this->assertStringContainsString('CadreMaster::query()', $controller);
        $this->assertStringContainsString('CadreSubMaster::query()', $controller);

        // CO4C3.2 superseded the earlier wide-table Choice Lineage column
        // with full-width stacked candidate cards.
        $this->assertStringContainsString('Current Candidate', $view);
        $this->assertStringContainsString('Previous BCS Match', $view);
        $this->assertStringContainsString('Input Effective Choice', $view);
        $this->assertStringContainsString('Allocation-ready Choice', $view);
        $this->assertStringContainsString('choice-optimization.partials.choice-code-lane', $view);
        $this->assertStringNotContainsString('<th style="min-width:520px">Choice Lineage</th>', $view);

        // CO4C3.3 finalized wrapped vertical chips to avoid horizontal scrolling.
        $this->assertStringContainsString('d-flex flex-wrap', $partial);
        $this->assertStringContainsString('d-inline-flex flex-column', $partial);
        $this->assertStringNotContainsString('overflow-auto', $partial);
        $this->assertStringContainsString('$choiceCodeAbbrMap[(int)$code]', $partial);
        $this->assertStringContainsString('str_pad((string)($i + 1), 2, \'0\'', $partial);
    }

    public function test_detail_uses_same_choice_lane_and_shows_candidate_name(): void
    {
        $model = file_get_contents(app_path('Models/ChoiceOptimizationHistoricalChoice.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/historical-choice-show.blade.php'));

        $this->assertStringContainsString('public function registration(): BelongsTo', $model);
        $this->assertStringContainsString('$choice->load(\'registration\')', $controller);
        $this->assertStringContainsString('$choice->registration?->name', $view);

        $this->assertStringContainsString('Input Effective Choice', $view);
        $this->assertStringContainsString('Removed by Historical Cutoff', $view);
        $this->assertStringContainsString('Final Allocation-ready Choice', $view);
        $this->assertStringContainsString('choice-optimization.partials.choice-code-lane', $view);
    }
}
