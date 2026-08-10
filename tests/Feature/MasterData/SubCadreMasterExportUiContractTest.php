<?php

namespace Tests\Feature\MasterData;

use App\Enums\UserRole;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubCadreMasterExportUiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_sub_cadre_index_exposes_pdf_and_excel_export_actions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $parent = CadreMaster::query()->create([
            'cadre_code' => 610,
            'cadre_abbr' => 'GEDU',
            'cadre_name' => 'BCS (General Education)',
            'cadre_name_bn' => 'বিসিএস (সাধারণ শিক্ষা)',
            'cadre_type' => 'TT',
            'display_order' => 10,
            'is_active' => true,
        ]);

        CadreSubMaster::query()->create([
            'parent_cadre_id' => $parent->id,
            'sub_cadre_code' => 611,
            'sub_cadre_abbr' => 'BNGL',
            'post_name' => 'Lecturer (Bangla)',
            'post_name_bn' => 'প্রভাষক (বাংলা)',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('cadre-sub-masters.index'))
            ->assertOk()
            ->assertSee('Export PDF')
            ->assertSee('Export Excel');
    }

    public function test_sub_cadre_excel_export_is_available(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('master-data.exports.excel', 'cadre-sub-masters'))
            ->assertOk()
            ->assertDownload();
    }
}
