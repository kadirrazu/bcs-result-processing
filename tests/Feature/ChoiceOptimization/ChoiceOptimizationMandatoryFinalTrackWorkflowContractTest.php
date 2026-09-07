<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoiceOptimizationMandatoryFinalTrackWorkflowContractTest extends TestCase
{
    #[Test]
    public function choice_validation_defers_written_track_but_keeps_other_eligibility_rules(): void
    {
        $code = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationEngine.php'));
        $this->assertStringContainsString('Written surviving-track mismatch is NOT a Choice Validation', $code);
        $this->assertStringNotContainsString('ChoiceValidationReason::TrackNotAllowed->value;', $code);
        $this->assertStringContainsString('BachelorAndPrsMismatch', $code);
    }

    #[Test]
    public function historical_cutoff_runs_before_mandatory_written_track_projection(): void
    {
        $code = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php'));
        $historical = strpos($code, '$postHistoricalCodes = array_values($result[\'final\']);');
        $track = strpos($code, '$this->trackProjection->project(');
        $this->assertNotFalse($historical);
        $this->assertNotFalse($track);
        $this->assertLessThan($track, $historical);
        $this->assertStringContainsString("'written_track_filter_order' => 'LAST'", $code);
    }

    #[Test]
    public function allocation_requires_finalized_choice_optimization_and_does_not_fallback_to_validated_choice(): void
    {
        $code = file_get_contents(app_path('Services/Allocation/AllocationInputFreezeService.php'));
        $this->assertStringContainsString("\$choiceSource = 'choice_optimization';", $code);
        $this->assertStringContainsString('Choice Optimization is mandatory and must be current/finalized', $code);
        $this->assertStringNotContainsString("\$choiceSource = 'choice_validation';", $code);
    }
}
