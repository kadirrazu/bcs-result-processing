<?php

namespace Tests\Feature\MasterData;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verify import pages and templates remain protected and reachable. */
final class MasterDataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_import_page(): void
    {
        $this->get('/master-data/imports/bachelor-subjects')->assertRedirect('/login');
    }

    public function test_admin_can_open_import_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get('/master-data/imports/bachelor-subjects')->assertOk();
    }
}
