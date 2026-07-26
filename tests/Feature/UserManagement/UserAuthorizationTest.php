<?php

namespace Tests\Feature\UserManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify role-based access to user administration routes.
 */
class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_user_directory(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_operator_cannot_view_user_directory(): void
    {
        $operator = User::factory()->create();

        $this->actingAs($operator)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_viewer_cannot_open_user_creation_form(): void
    {
        $viewer = User::factory()->create([
            'role' => 'viewer',
        ]);

        $this->actingAs($viewer)
            ->get(route('users.create'))
            ->assertForbidden();
    }
}
