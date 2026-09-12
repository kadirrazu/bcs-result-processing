<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class SpecialRequirementManualVerificationContractTest extends TestCase
{
    public function test_circular_special_requirement_is_ui_only_optional_metadata_with_comment_required_for_yes(): void
    {
        $migration = file_get_contents(base_path('database/examination-migrations/2026_09_12_230000_add_special_requirements_and_manual_allocation_choice_adjustment.php'));
        $request = file_get_contents(app_path('Http/Requests/StoreCircularEntryRequest.php'));
        $form = file_get_contents(resource_path('views/circular/_form.blade.php'));
        $spreadsheet = file_get_contents(app_path('Services/Circular/CircularSpreadsheetService.php'));

        self::assertStringContainsString("boolean('special_requirement')", $migration);
        self::assertStringContainsString("default(false)", $migration);
        self::assertStringContainsString("asr_a5_idx", $migration);
        self::assertStringContainsString("asr_registration_idx", $migration);
        self::assertStringNotContainsString("allocation_special_requirement_review_events_allocation_a5_run_id_index", $migration);
        self::assertStringContainsString('required_if:special_requirement,1', $request);
        self::assertStringContainsString('UI-only metadata; not imported from Excel', $form);
        self::assertStringNotContainsString('special_requirement', $spreadsheet);
    }

    public function test_a6_review_is_optional_and_failed_review_does_not_directly_stale_allocation(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $view = file_get_contents(resource_path('views/allocation/a6/special-requirements.blade.php'));

        self::assertStringContainsString('specialRequirements(', $controller);
        self::assertStringContainsString('reviewSpecialRequirement(', $controller);
        self::assertStringContainsString("'status'=>", str_replace(' ', '', $controller));
        self::assertStringContainsString("\$validated['status']", $controller);
        self::assertStringNotContainsString('staleA3AndA4', $controller);
        self::assertStringContainsString('No action leaves the finalized allocation unchanged', $view);
        self::assertStringContainsString('FAILED does not itself alter allocation', $view);
    }
}
