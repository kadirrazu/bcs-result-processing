<?php

namespace Tests\Feature\MasterData;

use App\Enums\UserRole;
use App\Models\BachelorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Verify import pages, previews and pagination remain protected and functional. */
final class MasterDataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_import_page(): void
    {
        $this->get('/master-data/imports/bachelor-subjects')->assertRedirect('/login');
    }

    public function test_admin_can_open_import_page_and_download_template(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('master-data.imports.create', 'bachelor-subjects'))
            ->assertOk()
            ->assertSee('Import Bachelor Subjects');

        $this->actingAs($admin)
            ->get(route('master-data.imports.template', 'bachelor-subjects'))
            ->assertOk()
            ->assertDownload('bachelor-subjects-template.xlsx');
    }

    public function test_admin_can_preview_and_confirm_subject_import(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $file = UploadedFile::fake()->createWithContent(
            'bachelor-subjects.csv',
            "subject_code,subject_name,is_active\n001,Bangla,1\n002,English,1\n",
        );

        $response = $this->actingAs($admin)->post(
            route('master-data.imports.preview', 'bachelor-subjects'),
            ['file' => $file, 'mode' => 'insert'],
        );

        $response->assertOk()->assertSee('Import Preview')->assertSee('Bangla');
        $token = $response->viewData('token');

        $this->actingAs($admin)
            ->post(route('master-data.imports.store', 'bachelor-subjects'), ['token' => $token])
            ->assertRedirect(route('bachelor-subjects.index'));

        $this->assertDatabaseHas('bachelor_subjects', ['subject_code' => '001', 'subject_name' => 'Bangla']);
        $this->assertDatabaseHas('bachelor_subjects', ['subject_code' => '002', 'subject_name' => 'English']);
    }

    public function test_page_size_is_limited_to_supported_values(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        foreach (range(1, 30) as $index) {
            BachelorSubject::query()->create([
                'subject_code' => str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'subject_name' => 'Subject '.$index,
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('bachelor-subjects.index', ['per_page' => 50]))
            ->assertOk()
            ->assertViewHas('perPage', 50);

        $this->actingAs($admin)
            ->get(route('bachelor-subjects.index', ['per_page' => 100000]))
            ->assertOk()
            ->assertViewHas('perPage', 25);
    }
}
