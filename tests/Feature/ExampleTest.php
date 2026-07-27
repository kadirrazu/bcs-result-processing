<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Confirm that the application entry point redirects to the dashboard
     * and that guests cannot access the protected dashboard.
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
