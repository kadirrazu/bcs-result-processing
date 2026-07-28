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
        $this->actingAs($admin)->post(route('cadre-masters.store'), ['cadre_code' => 110, 'cadre_abbr' => 'admn', 'cadre_title' => 'BCS (Administration)', 'cadre_title_bn' => 'বিসিএস (প্রশাসন)', 'cadre_type' => 'GG', 'display_order' => 1, 'is_active' => 1])->assertRedirect(route('cadre-masters.index'));
        $record = CadreMaster::query()->firstOrFail();
        $this->assertSame('ADMN', $record->cadre_abbr);
        $this->actingAs($admin)->put(route('cadre-masters.update', $record), ['cadre_code' => 110, 'cadre_abbr' => 'ADMN', 'cadre_title' => 'Administration', 'cadre_title_bn' => 'প্রশাসন', 'cadre_type' => 'GG', 'display_order' => 2, 'is_active' => 0])->assertRedirect(route('cadre-masters.index'));
        $this->assertDatabaseHas('cadre_masters', ['cadre_code' => 110, 'display_order' => 2, 'is_active' => false]);
    }

    public function test_cadre_code_and_abbreviation_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        CadreMaster::query()->create(['cadre_code' => 110, 'cadre_abbr' => 'ADMN', 'cadre_title' => 'A', 'cadre_title_bn' => 'ক', 'cadre_type' => 'GG', 'display_order' => 1, 'is_active' => true]);
        $this->actingAs($admin)->post(route('cadre-masters.store'), ['cadre_code' => 110, 'cadre_abbr' => 'ADMN', 'cadre_title' => 'B', 'cadre_title_bn' => 'খ', 'cadre_type' => 'GG', 'display_order' => 2, 'is_active' => 1])->assertSessionHasErrors(['cadre_code', 'cadre_abbr']);
    }
}
