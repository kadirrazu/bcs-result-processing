<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo39ValidationTransparencyRevalidationContractTest extends TestCase
{
    public function test_batch_review_is_minimal_and_full_validation_trace_moves_to_individual_detail(): void
    {
        $batchView = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));
        $detailView = file_get_contents(resource_path('views/choice-optimization/omr-row-detail.blade.php'));
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrValidationService.php'));

        $this->assertStringContainsString('Validated OMR Choice:', $batchView);
        $this->assertStringContainsString('View validation trace', $batchView);
        $this->assertStringNotContainsString('JSON_PRETTY_PRINT', $batchView);
        $this->assertStringNotContainsString('<pre', $batchView);

        $this->assertStringContainsString('Choice Lineage', $detailView);
        $this->assertStringContainsString('Raw OMR Choice', $detailView);
        $this->assertStringContainsString('Expanded / Validated OMR Choice', $detailView);
        $this->assertStringContainsString('Expansion / Removal Details', $detailView);
        $this->assertStringContainsString('Human-readable trace of what happened to each OMR choice and why.', $detailView);
        $this->assertStringContainsString('Operator / Resolution Audit', $detailView);

        $this->assertStringContainsString('ChoiceValidationEngine', $service);
        $this->assertStringContainsString('validated_omr_choice_codes', $service);
        $this->assertStringContainsString('choice_validation_details', $service);
    }
}
