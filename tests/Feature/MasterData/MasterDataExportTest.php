<?php

namespace Tests\Feature\MasterData;

use App\Enums\UserRole;
use App\Models\BachelorSubject;
use App\Models\CadreMaster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MasterDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_import_compatible_bachelor_subject_excel_export(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        BachelorSubject::query()->create(['subject_code' => '001', 'subject_name' => 'Bangla', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('master-data.exports.excel', 'bachelor-subjects'));

        $response->assertOk();
        $response->assertDownload();
    }

    public function test_non_admin_cannot_export_master_data(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($user)
            ->get(route('master-data.exports.excel', 'cadre-masters'))
            ->assertForbidden();
    }

    public function test_cadre_index_displays_global_serial_number_and_export_actions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        CadreMaster::query()->create([
            'cadre_code' => 110,
            'cadre_abbr' => 'ADMN',
            'cadre_name' => 'BCS (Administration)',
            'cadre_name_bn' => 'বিসিএস (প্রশাসন)',
            'cadre_type' => 'GG',
            'display_order' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('cadre-masters.index'))
            ->assertOk()
            ->assertSee('SL')
            ->assertSee('Export PDF')
            ->assertSee('Export Excel');
    }
}
