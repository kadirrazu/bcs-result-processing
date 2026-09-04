<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChoiceOptimizationOmrReuseAndProcessingBoardStateHotfixContractTest extends TestCase
{
    #[Test]
    public function latest_approved_omr_can_be_revalidated_without_reupload(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $omrView = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));
        $landing = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));

        $this->assertStringContainsString("['approved', 'validated', 'needs_review', 'validation_failed']", $controller);
        $this->assertStringContainsString('Only the latest approved OMR batch can be re-validated', $controller);
        $this->assertStringContainsString('Re-validate Against Current Choice Validation', $omrView);
        $this->assertStringContainsString('Re-validate Latest OMR Batch #', $landing);
        $this->assertStringContainsString('no OMR re-upload is required', $landing);
    }

    #[Test]
    public function processing_board_reports_current_authority_not_only_last_stored_stage(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $landing = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));

        $this->assertStringContainsString('choiceOptimizationBoardState', $controller);
        $this->assertStringContainsString('OMR RE-VALIDATION REQUIRED', $controller);
        $this->assertStringContainsString('OMR RE-VALIDATING', $controller);
        $this->assertStringContainsString('OMR RE-APPROVAL REQUIRED', $controller);
        $this->assertStringContainsString('STALE / RE-PROCESS REQUIRED', $controller);
        $this->assertStringContainsString('Effective State', $landing);
        $this->assertStringContainsString('Stored stage:', $landing);
        $this->assertStringContainsString('Stale reason:', $landing);
    }
}
