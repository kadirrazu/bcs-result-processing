<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c37CanonicalHashOrderHotfixContractTest extends TestCase
{
    public function test_processing_and_database_output_hashes_use_same_registration_order(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php')
        );

        $dbOrder = strpos($service, "->orderBy('registration_id')");
        $processingSort = strpos($service, 'usort($rows, static fn (array $a, array $b): int =>');
        $registrationCompare = strpos(
            $service,
            "((int) (\$a['registration_id'] ?? 0)) <=> ((int) (\$b['registration_id'] ?? 0))"
        );

        $this->assertNotFalse($dbOrder);
        $this->assertNotFalse($processingSort);
        $this->assertNotFalse($registrationCompare);
        $this->assertStringContainsString('canonicalOutputRow', $service);
    }

    public function test_finalization_failed_page_does_not_duplicate_same_reason_as_stale_alert(): void
    {
        $view = file_get_contents(
            resource_path('views/choice-optimization/historical-choices-index.blade.php')
        );

        $this->assertStringContainsString(
            "@if((string)\$state->status === 'finalization_failed')",
            $view
        );
        $this->assertStringContainsString(
            '@elseif($state->is_stale && $state->stale_reason)',
            $view
        );
        $this->assertStringContainsString('Finalization failed:', $view);
    }
}
