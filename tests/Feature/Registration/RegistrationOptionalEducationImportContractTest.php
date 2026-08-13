<?php

namespace Tests\Feature\Registration;

use Tests\TestCase;

class RegistrationOptionalEducationImportContractTest extends TestCase
{
    public function test_new_education_headers_are_optional_and_rolls_preserve_text_identity(): void
    {
        $config = require config_path('registrations.php');
        $template = file_get_contents(app_path('Services/Registrations/RegistrationTemplateService.php'));
        $staging = file_get_contents(app_path('Services/Registrations/RegistrationStagingValidationService.php'));
        $request = file_get_contents(app_path('Http/Requests/StoreRegistrationRequest.php'));

        $this->assertSame(
            ['ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'graduation_year'],
            $config['optional_headers']
        );
        foreach ($config['required_headers'] as $header) {
            $this->assertContains($header, $config['headers']);
        }

        $this->assertStringContainsString('NumberFormat::FORMAT_TEXT', $template);
        $this->assertStringContainsString('nullableYear($row->ssc_year, \'SSC_YEAR\'', $staging);
        $this->assertStringContainsString('nullableYear($row->hsc_year, \'HSC_YEAR\'', $staging);
        $this->assertStringContainsString('nullableYear($row->graduation_year, \'GRADUATION_YEAR\'', $staging);
        $this->assertStringContainsString("'ssc_roll' => ['nullable', 'string', 'max:30']", $request);
        $this->assertStringContainsString("'hsc_roll' => ['nullable', 'string', 'max:30']", $request);
    }
}
