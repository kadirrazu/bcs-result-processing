<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo39ValidationTransparencyRevalidationContractTest extends TestCase
{
    public function test_omr_review_exposes_validation_transparency_and_revalidation(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrValidationService.php'));

        $this->assertStringContainsString('OMR Choice Validation Result', $view);
        $this->assertStringContainsString('Raw OMR Choice', $view);
        $this->assertStringContainsString('Expanded / Validated OMR Choice', $view);
        $this->assertStringContainsString('Validation Errors', $view);
        $this->assertStringContainsString('Warnings / Review Notes', $view);
        $this->assertStringContainsString('Expansion / Removal Details', $view);
        $this->assertStringContainsString('OMR Choice is fully validated and safe for downstream use.', $view);
        $this->assertStringContainsString('OMR Choice is NOT eligible for override / Allocation', $view);
        $this->assertStringContainsString('Re-validate OMR Choices', $view);

        // Full validation continues to use the shared Choice Validation engine/rules.
        $this->assertStringContainsString('ChoiceValidationEngine', $service);
        $this->assertStringContainsString('validated_omr_choice_codes', $service);
        $this->assertStringContainsString('choice_validation_details', $service);
    }
}
