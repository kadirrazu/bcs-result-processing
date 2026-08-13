<?php

namespace Tests\Feature\Registration;

use Tests\TestCase;

class RegistrationEducationIdentityUpgradeContractTest extends TestCase
{
    public function test_nullable_education_identity_fields_are_wired_through_registration_and_import(): void
    {
        $migration = file_get_contents(base_path('database/examination-migrations/2026_08_13_235500_add_education_identity_fields_to_registrations.php'));
        $model = file_get_contents(app_path('Models/Registration.php'));
        $import = file_get_contents(app_path('Services/Registrations/RegistrationImportService.php'));
        $approval = file_get_contents(app_path('Services/Registrations/RegistrationApprovalService.php'));
        $form = file_get_contents(resource_path('views/registrations/_form.blade.php'));
        $show = file_get_contents(resource_path('views/registrations/show.blade.php'));
        $updateAction = file_get_contents(app_path('Actions/Registrations/UpdateRegistrationAction.php'));
        $approvalService = file_get_contents(app_path('Services/Registrations/RegistrationApprovalService.php'));

        foreach (['ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'graduation_year'] as $field) {
            $this->assertStringContainsString("'{$field}'", $model);
            $this->assertStringContainsString("'{$field}'", $approval);
            $this->assertStringContainsString('name="'.$field.'"', $form);
            $this->assertStringContainsString('$registration->'.$field, $show);
        }

        $this->assertStringContainsString("string('ssc_roll', 30)->nullable()", $migration);
        $this->assertStringContainsString("string('hsc_roll', 30)->nullable()", $migration);
        $this->assertStringContainsString("unsignedSmallInteger('graduation_year')->nullable()", $migration);
        $this->assertStringContainsString("config('registrations.required_headers'", $import);
        $this->assertStringContainsString('array_diff($required, $headers)', $import);
        $this->assertStringContainsString('auxiliaryIdentityFields()', $updateAction);
        $this->assertStringContainsString('$registration->timestamps = false', $updateAction);
        $this->assertStringContainsString('businessRelevantChanged', $approvalService);
        $this->assertStringContainsString("'ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'graduation_year'", $approvalService);
    }
}
