<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo314OmrAutoSanitizeApprovalContractTest extends TestCase
{
    public function test_repairable_omr_choice_problems_become_warnings_and_valid_clean_output_can_be_approved(): void
    {
        $validation = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrValidationService.php')
        );
        $approval = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrApprovalService.php')
        );

        $this->assertStringContainsString('CHOICE_SEQUENCE_GAP_AUTO_REPAIRED', $validation);
        $this->assertStringContainsString('CHOICE_REASSEMBLED', $validation);
        $this->assertStringContainsString("'result'] ?? null) === 'removed'", $validation);
        $this->assertStringContainsString("_AUTO_REMOVED", $validation);
        $this->assertStringContainsString('source_position', $validation);
        $this->assertStringContainsString('output_position', $validation);
        $this->assertStringContainsString('automatic sanitization', $validation);

        // Approval remains blocked only by unresolved row states, not warnings.
        $this->assertStringContainsString(
            "->whereIn('validation_status', ['invalid', 'conflict', 'decision_review', 'pending'])",
            $approval
        );
        $this->assertStringNotContainsString("validation_warnings')->count()", $approval);

        // Effective YES must still be a genuinely clean validated sequence.
        $this->assertStringContainsString(
            '(string) $omr->choice_validation_status !== \'valid\' || $override === []',
            $approval
        );
        $this->assertStringContainsString(
            'warning-level automatic sanitization/re-assembly action(s)',
            $approval
        );
    }
}
