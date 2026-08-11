<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceImportProgressPerformanceContractTest extends TestCase
{
    public function test_choice_staging_uses_configured_chunks_and_updates_progress(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceValidation/ChoiceSourceImportService.php'));
        $config = file_get_contents(config_path('choice-validation.php'));

        self::assertStringContainsString("CHOICE_STAGING_CHUNK_SIZE", $config);
        self::assertStringContainsString("CHOICE_VALIDATION_CHUNK_SIZE", $config);
        self::assertStringContainsString("quickTotalRows", $service);
        self::assertStringContainsString("updateProgress", $service);
        self::assertStringContainsString("choice-validation.staging_chunk_size", $service);
    }

    public function test_choice_import_review_uses_json_progress_polling_instead_of_full_page_refresh_loop(): void
    {
        $routes = file_get_contents(base_path('routes/choice-validation.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));
        $view = file_get_contents(resource_path('views/choice-validation/import-show.blade.php'));

        self::assertStringContainsString("/import/{batch}/status", $routes);
        self::assertStringContainsString("function importStatus", $controller);
        self::assertStringContainsString("Choice Staging Progress", $view);
        self::assertStringContainsString("choice-progress-bar", $view);
        self::assertStringContainsString("fetch(panel.dataset.statusUrl", $view);
        self::assertStringNotContainsString("window.location.reload(), 1800", $view);
    }
}
