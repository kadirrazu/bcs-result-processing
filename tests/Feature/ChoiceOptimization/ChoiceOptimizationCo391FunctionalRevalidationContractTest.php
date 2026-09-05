<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo391FunctionalRevalidationContractTest extends TestCase
{
    public function test_revalidation_is_a_real_queue_action_and_is_visible_after_validation(): void
    {
        $routes = file_get_contents(base_path('routes/choice-optimization.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));

        $this->assertStringContainsString("'/omr/{batch}/revalidate'", $routes);
        $this->assertStringContainsString("->name('omr.revalidate')", $routes);
        $this->assertStringContainsString('public function revalidateOmr(', $controller);
        $this->assertStringContainsString('private function queueOmrValidation(', $controller);
        $this->assertStringContainsString("'choice_validation_status' => 'pending'", $controller);
        $this->assertStringContainsString("'validated_omr_choice_codes' => null", $controller);
        $this->assertStringContainsString("'status' => 'validation_queued'", $controller);
        $this->assertStringContainsString('ProcessChoiceOptimizationOmrValidation::dispatch(', $controller);

        $this->assertStringContainsString('choice-optimization.omr.revalidate', $view);
        $this->assertStringContainsString('Re-validate OMR Choices', $view);
        $this->assertStringContainsString('Validation in progress…', $view);
        $this->assertStringContainsString('Queue OMR Re-validation', $view);

        $this->assertStringContainsString('Processing Action', $view);
        $this->assertStringContainsString('Validated OMR Choice:', $view);
        $this->assertStringContainsString('View validation trace', $view);
    }
}
