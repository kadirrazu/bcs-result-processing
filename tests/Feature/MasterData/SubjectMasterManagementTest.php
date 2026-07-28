<?php

namespace Tests\Feature\MasterData;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SubjectMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_both_subject_master_types(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->actingAs($admin)->post(route('bachelor-subjects.store'), ['subject_code' => '001', 'subject_name' => 'Bangla', 'is_active' => 1])->assertRedirect(route('bachelor-subjects.index'));
        $this->actingAs($admin)->post(route('post-related-subjects.store'), ['subject_code' => 'MEDI', 'subject_name' => 'Medical Science', 'is_active' => 1])->assertRedirect(route('post-related-subjects.index'));
        $this->assertDatabaseHas('bachelor_subjects', ['subject_code' => '001']);
        $this->assertDatabaseHas('post_related_subjects', ['subject_code' => 'MEDI']);
    }

    public function test_subject_codes_are_unique_within_each_master(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $payload = ['subject_code' => '101', 'subject_name' => 'Subject', 'is_active' => 1];
        $this->actingAs($admin)->post(route('bachelor-subjects.store'), $payload);
        $this->actingAs($admin)->post(route('bachelor-subjects.store'), $payload)->assertSessionHasErrors('subject_code');
    }
}
