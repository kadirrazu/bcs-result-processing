<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationCandidateDetailArrayTypeHotfixTest extends TestCase
{
    public function test_choice_columns_array_is_wrapped_before_using_collection_contains(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));

        self::assertStringContainsString('collect($choiceColumns)->contains(', $controller);
        self::assertStringNotContainsString('$choiceColumns->contains(', $controller);
    }
}
