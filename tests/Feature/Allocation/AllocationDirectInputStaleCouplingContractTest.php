<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationDirectInputStaleCouplingContractTest extends TestCase
{
    public function test_direct_authority_changes_stale_a2_and_all_downstream_allocation_phases(): void
    {
        $stale = file_get_contents(app_path('Services/Allocation/AllocationRunStaleService.php'));
        $meritFinalization = file_get_contents(app_path('Services/Merit/MeritFinalizationService.php'));
        $choiceFinalization = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceFinalizationService.php'));
        $choiceValidationFinalization = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationFinalizationService.php'));

        self::assertStringContainsString('staleFromDirectInputChange', $stale);
        self::assertStringContainsString("['status' => 'stale']", $stale);
        self::assertStringContainsString('staleA3AndA4(', $stale);
        self::assertStringContainsString("\$this->downstream->propagate('merit'", $meritFinalization);
        self::assertStringContainsString("'choice_optimization'", $choiceFinalization);
        self::assertStringContainsString('Merit remains current.', $choiceValidationFinalization);
        self::assertStringNotContainsString('Merit generated from an older Choice Validation must be regenerated.', $choiceValidationFinalization);
    }
}
