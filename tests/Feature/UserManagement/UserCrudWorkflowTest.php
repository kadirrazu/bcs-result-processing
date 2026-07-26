<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserRole;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify the user module's application-layer CRUD workflow.
 */
class UserCrudWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $designation = $this->createDesignation('Assistant Director');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New Operator',
                'email' => 'operator@example.test',
                'designation_id' => $designation->id,
                'role' => UserRole::Operator->value,
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'operator@example.test',
            'designation_id' => $designation->id,
            'role' => UserRole::Operator->value,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_a_user_without_changing_the_password(): void
    {
        $admin = User::factory()->admin()->create();
        $designation = $this->createDesignation('Programmer');
        $user = User::factory()->create([
            'password' => 'OriginalPass123!',
        ]);
        $originalPassword = $user->password;

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'Updated Name',
                'email' => $user->email,
                'designation_id' => $designation->id,
                'role' => UserRole::Viewer->value,
                'password' => null,
                'password_confirmation' => null,
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $designation = $this->createDesignation('Administrator');
        $admin = User::factory()->admin()->create([
            'designation_id' => $designation->id,
        ]);

        $this->actingAs($admin)
            ->from(route('users.edit', $admin))
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'designation_id' => $designation->id,
                'role' => UserRole::Admin->value,
                'password' => null,
                'password_confirmation' => null,
                'is_active' => '0',
            ])
            ->assertRedirect(route('users.edit', $admin))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_user_directory_can_be_searched_by_designation(): void
    {
        $admin = User::factory()->admin()->create();
        $designation = $this->createDesignation('Systems Analyst');
        $matched = User::factory()->create([
            'name' => 'Matched User',
            'designation_id' => $designation->id,
        ]);
        User::factory()->create(['name' => 'Other User']);

        $this->actingAs($admin)
            ->get(route('users.index', ['search' => 'Systems Analyst']))
            ->assertOk()
            ->assertSee($matched->name)
            ->assertDontSee('Other User');
    }

    private function createDesignation(string $name): Designation
    {
        return Designation::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
