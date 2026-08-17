<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationVersionAllocationContractTest extends TestCase
{
    public function test_failed_or_aborted_run_version_is_never_reused(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceValidationController.php'));

        $this->assertStringContainsString("DB::connection('exam')->transaction", $controller);
        $this->assertStringContainsString('->lockForUpdate()', $controller);
        $this->assertStringContainsString(
            '->where(\'source_version\', $sourceVersion)',
            $controller
        );
        $this->assertStringContainsString("->max('validation_version')", $controller);
        $this->assertStringContainsString(
            '$version = max((int) $state->current_validation_version, $existingRunVersion) + 1;',
            $controller
        );
    }
}
