<?php

namespace Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify that inactive application users cannot authenticate.
 */
class InactiveUserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.test',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }
}
