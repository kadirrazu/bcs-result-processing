<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoiceOptimizationCo3OmrOverrideContractTest extends TestCase
{
    #[Test]
    public function co3_adds_operator_decision_and_effective_choice_foundation(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_08_23_000100_upgrade_choice_optimization_omr_co3.php'));
        $this->assertStringContainsString('effective_change_choice', $migration);
        $this->assertStringContainsString('decision_resolution', $migration);
        $this->assertStringContainsString('validated_omr_choice_codes', $migration);
        $this->assertStringContainsString('choice_optimization_effective_choices', $migration);
    }

    #[Test]
    public function no_with_options_requires_explicit_operator_choice_with_comparison_ui(): void
    {
        $resolver = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrDecisionResolutionService.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));

        $this->assertStringContainsString('consider_no_as_yes_keep_options', $resolver);
        $this->assertStringContainsString('keep_no_discard_options', $resolver);
        $this->assertStringContainsString('Finalized Validated Choice', $view);
        $this->assertStringContainsString('OMR Options', $view);
        $this->assertStringContainsString('Consider NO as YES and keep the OMR options', $view);
        $this->assertStringContainsString('Consider NO as NO and discard the OMR options', $view);
    }

    #[Test]
    public function omr_yes_choices_are_revalidated_and_approval_runs_in_queue(): void
    {
        $validation = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrValidationService.php'));
        $approvalJob = file_get_contents(app_path('Jobs/ProcessChoiceOptimizationOmrApproval.php'));
        $approval = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrApprovalService.php'));

        $this->assertStringContainsString('ChoiceValidationEngine', $validation);
        $this->assertStringContainsString('CircularFinalizedDatasetService', $validation);
        $this->assertStringContainsString('OMR_CHOICE_VALIDATION_FAILED', $validation);
        $this->assertStringContainsString('implements ShouldQueue', $approvalJob);
        $this->assertStringContainsString('OVERRIDDEN_BY_VIVA_OMR', $approval);
        $this->assertStringContainsString('UNCHANGED_FROM_VALIDATED_CHOICE', $approval);
        $this->assertStringContainsString('choice_optimization_effective_choices', $approval);
    }

    #[Test]
    public function development_reset_includes_co3_effective_choice_table(): void
    {
        $reset = file_get_contents(config_path('development-module-reset.php'));
        $this->assertStringContainsString('choice_optimization_effective_choices', $reset);
    }
}
