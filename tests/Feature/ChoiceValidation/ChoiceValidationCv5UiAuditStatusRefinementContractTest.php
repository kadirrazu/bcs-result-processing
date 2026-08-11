<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationCv5UiAuditStatusRefinementContractTest extends TestCase
{
    public function test_viva_fail_and_other_not_applicable_reasons_are_explicit(): void
    {
        $resolver = file_get_contents(app_path('Services/ChoiceValidation/ChoiceCandidateTrackResolver.php'));
        $engine = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationEngine.php'));
        $processing = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationProcessingService.php'));

        self::assertStringContainsString('not_applicable_due_to_fail_in_viva', $resolver);
        self::assertStringContainsString('CANDIDATE_FAILED_IN_VIVA', $resolver);
        self::assertStringContainsString('not_applicable_due_to_missing_viva_result', $resolver);
        self::assertStringContainsString('not_applicable_due_to_inactive_viva_result', $resolver);
        self::assertStringContainsString("\$track['status']", $engine);
        self::assertStringContainsString("str_starts_with((string) \$output['status'], 'not_applicable')", $processing);
    }

    public function test_result_page_shows_candidate_name_and_not_applicable_breakdown(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));
        $view = file_get_contents(resource_path('views/choice-validation/results.blade.php'));

        self::assertStringContainsString("with(['items','registration'])", $controller);
        self::assertStringContainsString('notApplicableBreakdown', $controller);
        self::assertStringContainsString('$row->registration?->name', $view);
        self::assertStringContainsString('Not Applicable — Failed in Viva', file_get_contents(app_path('Support/ChoiceValidationStatusPresenter.php')));
    }

    public function test_detail_page_shows_identity_and_full_old_to_new_audit(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/result-detail.blade.php'));

        self::assertStringContainsString('Candidate Name', $view);
        self::assertStringContainsString('$result->registration?->name', $view);
        self::assertStringContainsString('Old → New', $view);
        self::assertStringContainsString('$correction->before_snapshot', $view);
        self::assertStringContainsString('$correction->corrected_snapshot', $view);
        self::assertStringContainsString('$correction->reason', $view);
        self::assertStringContainsString('$correction->actor_name', $view);
    }

    public function test_landing_processing_board_is_simplified_to_four_operational_stages(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/index.blade.php'));

        self::assertStringContainsString('1. Source Import &amp; Validation', $view);
        self::assertStringContainsString('2. Source Approval &amp; Correction', $view);
        self::assertStringContainsString('3. Choice Validation &amp; Review', $view);
        self::assertStringContainsString('4. Finalization', $view);
        self::assertStringNotContainsString('5. Choice Validation Engine', $view);
        self::assertStringNotContainsString('6. Validation Review', $view);
        self::assertStringNotContainsString('7. Choice Validation Finalization', $view);
    }
}
