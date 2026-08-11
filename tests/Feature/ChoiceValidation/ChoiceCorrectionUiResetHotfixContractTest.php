<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceCorrectionUiResetHotfixContractTest extends TestCase
{
    public function test_shared_import_correction_model_does_not_write_updated_at(): void
    {
        $model = file_get_contents(app_path('Models/ImportCorrectionEntry.php'));

        self::assertStringContainsString('public $timestamps = false;', $model);
    }

    public function test_choice_correction_scope_matches_choice_validation_reset_module(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceValidation/ChoiceInvalidRowCorrectionService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));
        $config = file_get_contents(config_path('development-module-reset.php'));

        self::assertStringContainsString("'module' => 'choice_validation'", $service);
        self::assertStringContainsString("->where('module', 'choice_validation')", $controller);
        self::assertStringContainsString("'values' => ['choice_validation']", $config);
        self::assertStringNotContainsString("'values' => ['choice_source']", $config);
    }

    public function test_choice_landing_summary_cards_use_equal_height_layout(): void
    {
        $view = file_get_contents(resource_path('views/choice-validation/index.blade.php'));

        self::assertStringContainsString('align-items-stretch', $view);
        self::assertStringContainsString('card card-sm h-100 w-100', $view);
        self::assertStringContainsString('col-sm-6 col-lg-3 d-flex', $view);
    }
}
