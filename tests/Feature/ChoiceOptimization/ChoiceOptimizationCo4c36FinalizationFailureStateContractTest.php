<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c36FinalizationFailureStateContractTest extends TestCase
{
    public function test_all_finalization_verification_is_inside_failure_guard_and_cannot_leave_queue_state_stuck(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceFinalizationService.php')
        );
        $controller = file_get_contents(
            app_path('Http/Controllers/ChoiceOptimizationController.php')
        );
        $view = file_get_contents(
            resource_path('views/choice-optimization/historical-choices-index.blade.php')
        );

        $tryPos = strpos($service, 'try {');
        $inputPos = strpos($service, '$input = $this->input->snapshot();');
        $hashPos = strpos($service, '$outputHash = $this->historical->outputHashFromDatabase();');
        $catchPos = strpos($service, 'catch (\\Throwable $e)');

        $this->assertNotFalse($tryPos);
        $this->assertNotFalse($inputPos);
        $this->assertNotFalse($hashPos);
        $this->assertNotFalse($catchPos);

        $this->assertLessThan($inputPos, $tryPos);
        $this->assertLessThan($hashPos, $tryPos);
        $this->assertGreaterThan($hashPos, $catchPos);

        $this->assertStringContainsString("'status' => 'finalization_failed'", $service);
        $this->assertStringContainsString('\'stale_reason\' => $e->getMessage()', $service);
        $this->assertStringContainsString('CHOICE_OPTIMIZATION_FINALIZATION_FAILED', $service);

        $this->assertStringContainsString("'finalization_queued', 'finalizing'", $controller);
        $this->assertStringContainsString("Finalization failed:", $view);
    }
}
