<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoiceOptimizationCo2OmrIdentityContractTest extends TestCase
{
    #[Test]
    public function co2_omr_foundation_preserves_raw_source_and_separates_effective_registration(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_08_22_220000_create_choice_optimization_omr_import.php'));
        $this->assertStringContainsString('choice_optimization_omr_batches', $migration);
        $this->assertStringContainsString('choice_optimization_omr_staging', $migration);
        $this->assertStringContainsString('raw_payload', $migration);
        $this->assertStringContainsString('raw_reg', $migration);
        $this->assertStringContainsString('effective_reg', $migration);
        $this->assertStringContainsString('resolution_reason', $migration);
        $this->assertStringContainsString('resolved_by', $migration);
    }

    #[Test]
    public function co2_uses_queue_jobs_for_staging_and_validation_and_written_identity_conflicts(): void
    {
        $importJob = file_get_contents(app_path('Jobs/ProcessChoiceOptimizationOmrImport.php'));
        $validationJob = file_get_contents(app_path('Jobs/ProcessChoiceOptimizationOmrValidation.php'));
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationOmrValidationService.php'));

        $this->assertStringContainsString('implements ShouldQueue', $importJob);
        $this->assertStringContainsString('implements ShouldQueue', $validationJob);
        $this->assertStringContainsString('WrittenProcessingStatus::ResultFinalized', $service);
        $this->assertStringContainsString("whereNotNull('written_qualified_track')", $service);
        $this->assertStringContainsString('INVALID_OMR_REGISTRATION', $service);
        $this->assertStringContainsString('DUPLICATE_OMR_REGISTRATION', $service);
        $this->assertStringContainsString('WRITTEN_REGISTRATION_AMBIGUOUS', $service);
        $this->assertStringContainsString('NO_WITH_CHOICES_REQUIRES_OPERATOR_DECISION', $service);
        $this->assertStringContainsString('YES_REQUIRES_CHOICE', $service);
    }

    #[Test]
    public function co2_progress_uses_json_polling_not_timed_page_reload(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));

        $this->assertStringContainsString("fetch(url", $view);
        $this->assertStringContainsString('omrStatus', $controller);
        $this->assertStringNotContainsString('window.location.reload()', $view);
        $this->assertStringNotContainsString('setTimeout(() => window.location.reload()', $view);
    }
}
