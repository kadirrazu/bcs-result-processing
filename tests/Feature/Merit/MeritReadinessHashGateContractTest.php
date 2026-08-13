<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritReadinessHashGateContractTest extends TestCase
{
    public function test_merit_readiness_requires_hash_verified_circular_tabulation_and_choice_validation(): void
    {
        $service = file_get_contents(app_path('Services/Merit/MeritReadinessService.php'));
        $circular = file_get_contents(app_path('Services/Circular/CircularFinalizedDatasetService.php'));

        $this->assertStringContainsString('CircularFinalizedDatasetService $circular', $service);
        $this->assertStringContainsString('TabulationFinalizedDatasetService $tabulation', $service);
        $this->assertStringContainsString('ChoiceValidationFinalizedDatasetService $choices', $service);
        $this->assertStringContainsString("'dataset_hash'", $service);
        $this->assertStringContainsString('CHOICE_VALIDATION_CIRCULAR_VERSION_MISMATCH', $service);
        $this->assertStringContainsString('CIRCULAR_DATASET_HASH_MISMATCH', $circular);
        $this->assertStringContainsString('verifiedConfirmation()', $circular);
    }

    public function test_merit_readiness_does_not_consume_raw_academic_modules_directly(): void
    {
        $service = file_get_contents(app_path('Services/Merit/MeritReadinessService.php'));

        foreach (['Registration', 'PreliminaryResult', 'WrittenResult', 'VivaResult'] as $rawModel) {
            $this->assertStringNotContainsString("App\\Models\\{$rawModel}", $service);
        }
    }
}
