<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationCv5BoardCleanupContractTest extends TestCase
{
    public function test_landing_contains_only_the_four_stage_processing_board(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/index.blade.php'));

        self::assertSame(1, substr_count($view, 'Processing Status Board'));
        self::assertSame(1, substr_count($view, '1. Source Import &amp; Validation'));
        self::assertSame(1, substr_count($view, '2. Source Approval &amp; Correction'));
        self::assertSame(1, substr_count($view, '3. Choice Validation &amp; Review'));
        self::assertSame(1, substr_count($view, '4. Finalization'));

        self::assertStringNotContainsString('5. Choice Validation Engine', $view);
        self::assertStringNotContainsString('6. Validation Review', $view);
        self::assertStringNotContainsString('7. Choice Validation Finalization', $view);
    }

    public function test_manual_correction_button_preserves_cv5_review_contract(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/result-detail.blade.php'));

        self::assertStringContainsString('Save Correction & Revalidate Candidate', $view);
    }
}
