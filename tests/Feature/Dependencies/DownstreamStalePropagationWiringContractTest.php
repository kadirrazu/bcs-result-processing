<?php

namespace Tests\Feature\Dependencies;

use Tests\TestCase;

final class DownstreamStalePropagationWiringContractTest extends TestCase
{
    public function test_upstream_change_paths_propagate_to_real_downstream_states(): void
    {
        $circular = file_get_contents(app_path('Services/Circular/CircularDatasetService.php'));
        $circularFinal = file_get_contents(app_path('Services/Circular/CircularAuthorityWorkflowService.php'));
        $choiceApproval = file_get_contents(app_path('Services/ChoiceValidation/ChoiceSourceApprovalService.php'));
        $choiceCorrection = file_get_contents(app_path('Services/ChoiceValidation/ChoiceManualCorrectionService.php'));
        $choiceFinal = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationFinalizationService.php'));
        $vivaFinal = file_get_contents(app_path('Services/Viva/VivaFinalizationService.php'));
        $tabStale = file_get_contents(app_path('Services/Tabulation/TabulationStaleService.php'));

        $this->assertStringContainsString("propagate(\n            'circular'", $circular);
        $this->assertStringContainsString("propagate(\n                'circular'", $circularFinal);
        $this->assertStringContainsString("'choice_validation'", $choiceApproval);
        $this->assertStringContainsString("'choice_validation'", $choiceCorrection);
        $this->assertStringContainsString("'choice_validation'", $choiceFinal);
        $this->assertStringContainsString("'viva'", $vivaFinal);
        $this->assertStringContainsString("'tabulation'", $tabStale);
    }
}
