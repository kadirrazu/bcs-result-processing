<?php

namespace Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Verify successful authentication updates the user's login timestamp.
 */
class LastLoginTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_records_last_login_time(): void
    {
        Carbon::setTestNow('2026-07-26 22:30:00');

        $user = User::factory()->create([
            'email' => 'operator@example.test',
            'password' => 'password',
            'last_login_at' => null,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertSame(
            '2026-07-26 22:30:00',
            $user->fresh()->last_login_at?->format('Y-m-d H:i:s')
        );
    }
}
