<?php

namespace Tests\Feature\MasterData;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MasterDataAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_cannot_access_master_data(): void
    {
        foreach ([UserRole::Operator, UserRole::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('cadre-masters.index'))->assertForbidden();
            $this->actingAs($user)->get(route('bachelor-subjects.index'))->assertForbidden();
            $this->actingAs($user)->get(route('post-related-subjects.index'))->assertForbidden();
        }
    }
}
