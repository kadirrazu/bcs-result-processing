<?php

namespace Tests\Feature\ChoiceValidation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoicePartialApprovalCorrectionBoardContractTest extends TestCase
{
    #[Test]
    public function valid_rows_can_be_approved_while_invalid_rows_remain(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceValidation/ChoiceSourceApprovalService.php'));

        $this->assertStringContainsString("['validated', 'partially_approved']", $service);
        $this->assertStringNotContainsString('approval is blocked while invalid rows remain', $service);
        $this->assertStringContainsString("'partially_approved'", $service);
        $this->assertStringContainsString("'pending_invalid'", $service);
        $this->assertStringContainsString("'source_complete'", $service);
    }

    #[Test]
    public function invalid_rows_have_a_targeted_download_and_reupload_workflow(): void
    {
        $routes = file_get_contents(base_path('routes/choice-validation.php'));
        $service = file_get_contents(app_path('Services/ChoiceValidation/ChoiceInvalidRowCorrectionService.php'));

        $this->assertStringContainsString("name('import.invalid-rows')", $routes);
        $this->assertStringContainsString("name('import.correct-invalid')", $routes);
        $this->assertStringContainsString("'source_batch_id'", $service);
        $this->assertStringContainsString("'source_row'", $service);
        $this->assertStringContainsString("'validation_error'", $service);
        $this->assertStringContainsString("'module' => 'choice_validation'", $service);
    }

    #[Test]
    public function choice_landing_page_uses_a_processing_status_board(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/index.blade.php'));

        $this->assertStringContainsString('Processing Status Board', $view);
        $this->assertStringContainsString('Source Import &amp; Validation', $view);
        $this->assertStringContainsString('Source Approval &amp; Correction', $view);
        $this->assertStringContainsString('Choice Validation &amp; Review', $view);
        $this->assertStringContainsString('4. Finalization', $view);
        $this->assertStringNotContainsString('5. Choice Validation Engine', $view);
        $this->assertStringNotContainsString('6. Validation Review', $view);
        $this->assertStringNotContainsString('7. Choice Validation Finalization', $view);
    }

    #[Test]
    public function choice_reset_clears_choice_validation_correction_audits_by_scope(): void
    {
        $config = file_get_contents(config_path('development-module-reset.php'));
        $this->assertStringContainsString("'values' => ['choice_validation']", $config);
    }

    #[Test]
    public function choice_chunk_sizes_are_configuration_driven(): void
    {
        $config = file_get_contents(config_path('choice-validation.php'));
        $processing = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationProcessingService.php'));

        $this->assertStringContainsString('CHOICE_STAGING_CHUNK_SIZE', $config);
        $this->assertStringContainsString('CHOICE_VALIDATION_CHUNK_SIZE', $config);
        $this->assertStringContainsString('CHOICE_APPROVAL_CHUNK_SIZE', $config);
        $this->assertStringContainsString('CHOICE_PROCESSING_CHUNK_SIZE', $config);
        $this->assertStringContainsString("choice-validation.processing_chunk_size", $processing);
    }
}
