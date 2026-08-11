<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceSourceStageValidationFlowContractTest extends TestCase
{
    public function test_choice_source_import_and_validation_are_separate_operator_steps(): void
    {
        $import = file_get_contents(app_path('Services/ChoiceValidation/ChoiceSourceImportService.php'));
        $validation = file_get_contents(app_path('Services/ChoiceValidation/ChoiceSourceValidationService.php'));
        $routes = file_get_contents(base_path('routes/choice-validation.php'));
        $view = file_get_contents(resource_path('views/choice-validation/import-show.blade.php'));

        self::assertStringContainsString("'status' => 'staged'", $import);
        self::assertStringContainsString("'validation_status' => 'pending'", $import);
        self::assertStringContainsString("'status' => 'validated'", $validation);

        self::assertStringContainsString('/import/{batch}/validate', $routes);
        self::assertStringContainsString('Validate Source', $view);

        // CV2.1+: valid rows may be approved/merged even when row-level
        // invalid data remains. The old "Approve Source Dataset" label
        // was superseded by the partial-approval workflow.
        self::assertStringContainsString('Approve / Merge', $view);
        self::assertStringContainsString('Valid Rows', $view);
        self::assertStringContainsString('Download Invalid Rows', $view);
        self::assertStringContainsString('Re-upload &amp; Revalidate', $view);
    }
}
