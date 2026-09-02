<?php

namespace Tests\Feature\Registration;

use PHPUnit\Framework\TestCase;

class RegistrationCandidateFatherListingContractTest extends TestCase
{
    public function test_registration_listing_selects_and_displays_father_name_under_candidate(): void
    {
        $query = file_get_contents(app_path('Queries/Registrations/ListRegistrationsQuery.php'));
        $view = file_get_contents(resource_path('views/registrations/index.blade.php'));

        $this->assertStringContainsString("'father_name'", $query);
        $this->assertStringContainsString('$record->father_name', $view);
        $this->assertStringContainsString('text-secondary small', $view);
    }
}
