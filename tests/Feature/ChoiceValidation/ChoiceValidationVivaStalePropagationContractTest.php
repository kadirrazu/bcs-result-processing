<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationVivaStalePropagationContractTest extends TestCase
{
    public function test_viva_reprocessing_marks_existing_choice_validation_stale_and_readiness_synchronizes_it(): void
    {
        $stale = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationVivaStaleService.php')
        );
        $readiness = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationReadinessService.php')
        );
        $viva = file_get_contents(
            app_path('Services/Viva/VivaResultProcessingService.php')
        );

        $this->assertStringContainsString(
            "'status' => 'stale'",
            $stale
        );
        $this->assertStringContainsString(
            "'is_stale' => true",
            $stale
        );
        $this->assertStringContainsString(
            'VIVA_RESULT_CHANGED:',
            $stale
        );
        $this->assertStringContainsString(
            'CHOICE_VALIDATION_STALE_DUE_TO_VIVA_CHANGE',
            $stale
        );
        $this->assertStringContainsString(
            '$vivaProcessedAt->gt($choiceCompletedAt)',
            $stale
        );
        $this->assertStringContainsString(
            '$vivaFinalizedAt->gt($choiceCompletedAt)',
            $stale
        );

        $this->assertStringContainsString(
            '$this->vivaStale->synchronize();',
            $readiness
        );
        $this->assertStringContainsString(
            'ChoiceValidationVivaStaleService $choiceValidationStale',
            $viva
        );
        $this->assertStringContainsString(
            '$this->choiceValidationStale->synchronize($actorId);',
            $viva
        );
    }
}
