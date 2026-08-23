<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4bPreviousBcsRepositoryAuthorityContractTest extends TestCase
{
    public function test_repository_validation_is_queued_and_checks_identity_duplicates_dob_and_cadre_master(): void
    {
        $job = file_get_contents(app_path('Jobs/ProcessPreviousBcsRepositoryValidation.php'));
        $service = file_get_contents(app_path('Services/PreviousBcsRepository/PreviousBcsRepositoryValidationService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PreviousBcsRepositoryController.php'));

        $this->assertStringContainsString('implements ShouldQueue', $job);
        $this->assertStringContainsString("onQueue('imports')", $job);
        $this->assertStringContainsString('ProcessPreviousBcsRepositoryValidation::dispatch', $controller);

        $this->assertStringContainsString('DUPLICATE_PREVIOUS_BCS_REG', $service);
        $this->assertStringContainsString('DUPLICATE_CORE_IDENTITY', $service);
        $this->assertStringContainsString('SECONDARY_DOB_MISMATCH', $service);
        $this->assertStringContainsString('CADRE_MASTER_MISMATCH', $service);
        $this->assertStringContainsString('CadreMaster::query()', $service);
        $this->assertStringContainsString('CadreSubMaster::query()', $service);
        $this->assertStringContainsString('\'status\' => $invalid === 0 ? \'validated\' : \'validation_failed\'', $service);
        $this->assertStringContainsString('datasetHash', $service);
    }

    public function test_only_hash_verified_validated_dataset_can_become_effective_and_old_effective_is_superseded(): void
    {
        $authority = file_get_contents(app_path('Services/PreviousBcsRepository/PreviousBcsRepositoryAuthorityService.php'));
        $effective = file_get_contents(app_path('Services/PreviousBcsRepository/PreviousBcsEffectiveDatasetService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PreviousBcsRepositoryController.php'));
        $view = file_get_contents(resource_path('views/previous-bcs-repository/show.blade.php'));

        $this->assertStringContainsString("status !== 'validated'", $authority);
        $this->assertStringContainsString('invalid_rows', $authority);
        $this->assertStringContainsString('hash_equals', $authority);
        $this->assertStringContainsString("'status' => 'superseded'", $authority);
        $this->assertStringContainsString("'status' => 'effective'", $authority);
        $this->assertStringContainsString('current_effective_dataset_id', $authority);
        $this->assertStringContainsString('DATASET_MADE_EFFECTIVE', $authority);

        $this->assertStringContainsString("status !== 'effective'", $effective);
        $this->assertStringContainsString('currentEffectiveDataset', $effective);

        $this->assertStringContainsString("'confirmation' => ['required', 'in:EFFECTIVE']", $controller);
        $this->assertStringContainsString('Approve & Make Effective', $view);
        $this->assertStringContainsString('Type EFFECTIVE', $view);
    }

    public function test_validation_ui_uses_same_json_polling_completion_pattern(): void
    {
        $view = file_get_contents(resource_path('views/previous-bcs-repository/show.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PreviousBcsRepositoryController.php'));

        $this->assertStringContainsString("'validation_queued','validating'", $view);
        $this->assertStringContainsString('Validation in progress', $view);
        $this->assertStringContainsString('window.location.replace(window.location.href)', $view);
        $this->assertStringContainsString("'validation_queued', 'validating'", $controller);
    }
}
