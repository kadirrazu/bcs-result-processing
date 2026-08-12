<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

final class TabulationSourceDerivedVerificationContractTest extends TestCase
{
    public function test_source_to_derived_verification_is_read_only_and_explicit(): void
    {
        $service = file_get_contents(app_path('Services/Tabulation/TabulationSourceDerivedVerificationService.php'));

        $this->assertStringContainsString("'Preliminary Mark'", $service);
        $this->assertStringContainsString("'General Written Total'", $service);
        $this->assertStringContainsString("'Technical Written Total'", $service);
        $this->assertStringContainsString("'Viva Mark'", $service);
        $this->assertStringContainsString("'General P/F'", $service);
        $this->assertStringContainsString("'Technical P/F'", $service);
        $this->assertStringNotContainsString('update(', $service);
        $this->assertStringNotContainsString('save(', $service);
    }
}
