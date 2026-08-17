<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationFlashMessageSingleRenderContractTest extends TestCase
{
    public function test_choice_validation_views_rely_on_global_flash_partial_only(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $flash = file_get_contents(resource_path('views/layouts/partials/flash-messages.blade.php'));

        $this->assertStringContainsString(
            "@include('layouts.partials.flash-messages')",
            $layout
        );
        $this->assertStringContainsString("session('success')", $flash);
        $this->assertStringContainsString("session('error')", $flash);

        $views = [
            'choice-validation/index.blade.php',
            'choice-validation/final-report.blade.php',
            'choice-validation/finalization.blade.php',
            'choice-validation/import-show.blade.php',
            'choice-validation/result-detail.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));

            $this->assertStringNotContainsString(
                "session('success')",
                $contents,
                $view.' must not render the global success flash a second time.'
            );

            $this->assertStringNotContainsString(
                "session('error')",
                $contents,
                $view.' must not render the global error flash a second time.'
            );
        }

        $finalization = file_get_contents(
            resource_path('views/choice-validation/finalization.blade.php')
        );
        $this->assertStringContainsString('$errors->any()', $finalization);
        $this->assertStringContainsString('Finalization blocked.', $finalization);
    }
}
