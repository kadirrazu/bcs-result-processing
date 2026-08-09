<?php

namespace Tests\Feature\MasterData;

use App\Enums\UserRole;
use App\Models\CadreMaster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CadreMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_a_cadre_master(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->actingAs($admin)->post(route('cadre-masters.store'), ['cadre_code' => 110, 'cadre_abbr' => 'admn', 'cadre_name' => 'BCS (Administration)', 'cadre_name_bn' => 'বিসিএস (প্রশাসন)', 'post_name' => 'Assistant Commissioner', 'post_name_bn' => 'সহকারী কমিশনার', 'cadre_type' => 'GG', 'display_order' => 1, 'is_active' => 1])->assertRedirect(route('cadre-masters.index'));
        $record = CadreMaster::query()->firstOrFail();
        $this->assertSame('ADMN', $record->cadre_abbr);
        $this->actingAs($admin)->put(route('cadre-masters.update', $record), ['cadre_code' => 110, 'cadre_abbr' => 'ADMN', 'cadre_name' => 'Administration', 'cadre_name_bn' => 'প্রশাসন', 'post_name' => 'Assistant Commissioner', 'post_name_bn' => 'সহকারী কমিশনার', 'correction_reason' => 'Verified master correction', 'cadre_type' => 'GG', 'display_order' => 2, 'is_active' => 0])->assertRedirect(route('cadre-masters.index'));
        $this->assertDatabaseHas('cadre_masters', ['cadre_code' => 110, 'display_order' => 2, 'is_active' => false]);
    }

    public function test_cadre_code_and_abbreviation_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        CadreMaster::query()->create(['cadre_code' => 110, 'cadre_abbr' => 'ADMN', 'cadre_name' => 'A', 'cadre_name_bn' => 'ক', 'cadre_type' => 'GG', 'display_order' => 1, 'is_active' => true]);
        $this->actingAs($admin)->post(route('cadre-masters.store'), ['cadre_code' => 110, 'cadre_abbr' => 'ADMN', 'cadre_name' => 'B', 'cadre_name_bn' => 'খ', 'cadre_type' => 'GG', 'display_order' => 2, 'is_active' => 1])->assertSessionHasErrors(['cadre_code', 'cadre_abbr']);
    }
}
