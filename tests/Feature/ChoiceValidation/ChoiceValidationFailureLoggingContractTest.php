<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationFailureLoggingContractTest extends TestCase
{
    public function test_large_database_exception_never_masks_original_choice_validation_failure(): void
    {
        $job = file_get_contents(app_path('Jobs/ProcessChoiceValidation.php'));

        $this->assertStringContainsString(
            "Log::error('Choice Validation processing failed.'",
            $job
        );
        $this->assertStringContainsString(
            "'exception' => \$e",
            $job
        );
        $this->assertStringContainsString(
            "'failure_message' => mb_substr(\$e->getMessage(), 0, 8000)",
            $job
        );
        $this->assertStringContainsString(
            'throw $e;',
            $job
        );

        $this->assertStringNotContainsString(
            "'failure_message' => \$e->getMessage()",
            $job
        );
    }
}
