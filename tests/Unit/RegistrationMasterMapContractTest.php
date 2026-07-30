<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guard the registration import template contract from accidental division reintroduction. */
final class RegistrationMasterMapContractTest extends TestCase
{
    public function test_registration_import_does_not_accept_division_column(): void
    {
        $headers = require __DIR__.'/../../config/registrations.php';

        $this->assertNotContains('division', $headers['headers']);
        $this->assertContains('district', $headers['headers']);
        $this->assertContains('university', $headers['headers']);
    }
}
