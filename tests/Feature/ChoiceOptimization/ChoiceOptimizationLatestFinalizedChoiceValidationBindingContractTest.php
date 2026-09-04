<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoiceOptimizationLatestFinalizedChoiceValidationBindingContractTest extends TestCase
{
    #[Test]
    public function historical_input_never_silently_reuses_an_old_effective_choice_snapshot(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalInputService.php'));

        $this->assertStringContainsString('verifiedSummary()', $service);
        $this->assertStringContainsString('choice_validation_result_id', $service);
        $this->assertStringContainsString('validated_choice_codes', $service);
        $this->assertStringContainsString('STALE_EFFECTIVE_OVERRIDE_BLOCKED', $service);
        $this->assertStringContainsString('STALE_EFFECTIVE_FALLBACK_TO_LATEST_CV', $service);
        $this->assertStringContainsString("where('choice_source', 'viva_omr_override')", $service);
        $this->assertStringContainsString('$useEffective = (bool) $binding[\'use_effective\'];', $service);
        $this->assertStringContainsString('$source = \'finalized_validated_choice\';', $service);
    }

    #[Test]
    public function reprocess_is_server_side_gated_and_provenance_is_visible(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $landing = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $results = file_get_contents(resource_path('views/choice-optimization/historical-choices-index.blade.php'));

        $this->assertStringContainsString('assertReadyForOptimization()', $controller);
        $this->assertStringContainsString('inputBinding', $controller);
        $this->assertStringContainsString('Current Finalized Choice Validation', $landing);
        $this->assertStringContainsString('Last Optimization Used', $landing);
        $this->assertStringContainsString('Next Re-Process Input Binding', $landing);
        $this->assertStringContainsString('This Output Used', $results);
        $this->assertStringContainsString('Re-process blocked:', $results);
    }
}
