<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo311OmrVisualPolishContractTest extends TestCase
{
    public function test_batch_and_individual_omr_views_use_compact_identity_context_and_vertical_choice_chips(): void
    {
        $batch = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));
        $detail = file_get_contents(resource_path('views/choice-optimization/omr-row-detail.blade.php'));

        $this->assertStringContainsString('Reg:', $batch);
        $this->assertStringContainsString('co-reg-number', $batch);
        $this->assertStringContainsString('Category:', $batch);
        $this->assertStringContainsString('Written Track:', $batch);
        $this->assertStringContainsString('co-code-category', $batch);
        $this->assertStringContainsString('co-code-track', $batch);
        $this->assertStringContainsString('co-choice-code', $batch);
        $this->assertStringContainsString('co-different', $batch);

        $this->assertStringContainsString('co-detail-category', $detail);
        $this->assertStringContainsString('co-detail-track', $detail);
        $this->assertStringContainsString('co-detail-choice-pos', $detail);
        $this->assertStringContainsString('co-detail-choice-code', $detail);
        $this->assertStringContainsString("Choice:", $detail);
        $this->assertStringContainsString('co-different', $detail);
    }
}
