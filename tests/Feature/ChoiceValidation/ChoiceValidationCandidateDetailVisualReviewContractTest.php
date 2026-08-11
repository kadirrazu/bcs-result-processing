<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationCandidateDetailVisualReviewContractTest extends TestCase
{
    public function test_candidate_summary_exposes_registration_written_and_current_track_context(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));
        $view = file_get_contents(resource_path('views/choice-validation/result-detail.blade.php'));

        self::assertStringContainsString("WrittenResult::query()", $controller);
        self::assertStringContainsString("'registration'", $controller);
        self::assertStringContainsString('Original Category', $view);
        self::assertStringContainsString('Derived Category after Written', $view);
        self::assertStringContainsString('Current Track', $view);
        self::assertStringContainsString('Registration / User ID', $view);
        self::assertStringContainsString('Candidate Name', $view);
    }

    public function test_original_and_validated_choices_have_visual_removed_and_expanded_markers(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/result-detail.blade.php'));

        self::assertStringContainsString('Original Imported Choices', $view);
        self::assertStringContainsString('Validated Choices', $view);
        self::assertStringContainsString('Removed', $view);
        self::assertStringContainsString('Expanded / Derived', $view);
        self::assertStringContainsString('bg-red-lt text-red', $view);
        self::assertStringContainsString('bg-blue-lt text-blue', $view);
        self::assertStringContainsString('bg-green-lt text-green', $view);
    }

    public function test_resolution_trail_highlights_non_retained_rows(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/result-detail.blade.php'));

        self::assertStringContainsString("'removed' => 'table-danger'", $view);
        self::assertStringContainsString("'expanded' => 'table-info'", $view);
        self::assertStringContainsString('Source Choice Resolution Trail', $view);
    }

    public function test_raw_original_and_corrected_effective_choices_remain_distinct(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));
        $view = file_get_contents(resource_path('views/choice-validation/result-detail.blade.php'));

        self::assertStringContainsString('$originalChoices', $controller);
        self::assertStringContainsString('$effectiveChoices', $controller);
        self::assertStringContainsString('$hasEffectiveCorrection', $controller);
        self::assertStringContainsString('Effective Choices After Manual Correction', $view);
        self::assertStringContainsString('Manually Corrected', $view);
    }
}
