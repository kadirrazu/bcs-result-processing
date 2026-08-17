<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationResultStatusLengthContractTest extends TestCase
{
    public function test_choice_validation_result_status_column_supports_locked_long_status_tokens(): void
    {
        $foundation = file_get_contents(
            database_path('examination-migrations/2026_08_12_001500_create_choice_validation_processing.php')
        );
        $upgrade = file_get_contents(
            database_path('examination-migrations/2026_08_17_103500_expand_choice_validation_result_status.php')
        );
        $resolver = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceCandidateTrackResolver.php')
        );

        $this->assertStringContainsString("string('status',80)", str_replace(' ', '', $foundation));
        $this->assertStringContainsString('VARCHAR(80)', $upgrade);

        $this->assertStringContainsString('not_applicable_due_to_fail_in_viva', $resolver);
        $this->assertStringContainsString('not_applicable_due_to_missing_viva_result', $resolver);
        $this->assertStringContainsString('not_applicable_due_to_inactive_viva_result', $resolver);
        $this->assertStringContainsString('not_applicable_due_to_unresolved_written_track', $resolver);

        $this->assertLessThanOrEqual(
            80,
            strlen('not_applicable_due_to_unresolved_written_track')
        );
    }
}
