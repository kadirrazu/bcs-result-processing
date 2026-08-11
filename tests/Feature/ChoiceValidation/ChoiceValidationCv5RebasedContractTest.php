<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationCv5RebasedContractTest extends TestCase
{
    public function test_manual_correction_is_overlay_and_preserves_raw_source(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceValidation/ChoiceManualCorrectionService.php'));
        $resolver = file_get_contents(app_path('Services/ChoiceValidation/ChoiceEffectiveSourceResolver.php'));

        self::assertStringContainsString('ChoiceValidationManualCorrection::query()->create', $service);
        self::assertStringContainsString("'raw_import_preserved' => true", $service);
        self::assertStringContainsString('corrected_snapshot', $resolver);
        self::assertStringNotContainsString('$source->update(', $service);
    }

    public function test_full_validation_keeps_bulk_processing_and_uses_effective_corrected_source(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationProcessingService.php'));

        self::assertStringContainsString('ChoiceEffectiveSourceResolver $effectiveSource', $service);
        self::assertStringContainsString('$this->effectiveSource->preload($sources)', $service);
        self::assertStringContainsString('$this->effectiveSource->items($source)', $service);
        self::assertStringContainsString("table('choice_validation_results')->insert(\$resultRows)", $service);
        self::assertStringContainsString("table('choice_validation_items')->insert(\$itemChunk)", $service);
    }

    public function test_candidate_review_exposes_audited_correction_and_revalidation(): void
    {
        $routes = file_get_contents(base_path('routes/choice-validation.php'));
        $view = file_get_contents(resource_path('views/choice-validation/result-detail.blade.php'));

        self::assertStringContainsString('/result/{result}/correct', $routes);
        self::assertStringContainsString('Save Correction & Revalidate Candidate', $view);
        self::assertStringContainsString('Manual Correction History', $view);
        self::assertStringContainsString('Correction Reason', $view);
    }

    public function test_v214_progress_polling_contract_is_preserved(): void
    {
        $routes = file_get_contents(base_path('routes/choice-validation.php'));
        $view = file_get_contents(resource_path('views/choice-validation/results.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));

        self::assertStringContainsString('/runs/{run}/progress', $routes);
        self::assertStringContainsString('choice-validation-progress-card', $view);
        self::assertStringNotContainsString('http-equiv="refresh"', $view);
        self::assertStringContainsString('function validationProgress', $controller);
    }

    public function test_choice_reset_registry_contains_manual_correction_table(): void
    {
        $tables = collect(config('development-module-reset.modules.choice_validation.tables', []));
        self::assertTrue($tables->contains('choice_validation_manual_corrections'));
    }
}
