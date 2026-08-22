<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoiceOptimizationCo33InteractiveReviewPolishContractTest extends TestCase
{
    #[Test]
    public function review_ui_shows_candidate_context_and_three_choice_lineages(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));

        $this->assertStringContainsString("with(['registration', 'source.items'])", $controller);
        $this->assertStringContainsString('registrationChoiceMap', $controller);
        $this->assertStringContainsString('candidateContextMap', $controller);
        $this->assertStringContainsString('Original Category', $view);
        $this->assertStringContainsString('Written Qualified Track', $view);
        $this->assertStringContainsString('Registration Choice', $view);
        $this->assertStringContainsString('Validated Choice', $view);
        $this->assertStringContainsString('OMR Choice', $view);
        $this->assertStringContainsString('Left to Right = Preference Order', $view);
        $this->assertStringContainsString('range(1, $choiceSlotCount)', $view);
    }

    #[Test]
    public function operator_confirmation_is_ajax_and_advances_to_next_candidate(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));

        $this->assertStringContainsString('RedirectResponse|JsonResponse', $controller);
        $this->assertStringContainsString('remaining_review_rows', $controller);
        $this->assertStringContainsString('remainingOmrOperatorReviews', $controller);
        $this->assertStringContainsString('co-review-form', $view);
        $this->assertStringContainsString("headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}", $view);
        $this->assertStringContainsString('co-resolved', $view);
        $this->assertStringContainsString('focusNextReview', $view);
        $this->assertStringContainsString('scrollIntoView', $view);
        $this->assertStringContainsString('Operator review complete.', $view);
        $this->assertStringContainsString('Queue OMR Re-validation', $view);
    }
}
