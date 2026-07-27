<?php

namespace Tests\Feature\Examinations;

use App\Data\ExaminationConnectionHealth;
use App\Enums\ExaminationStatus;
use App\Models\Examination;
use App\Models\User;
use App\Support\Examinations\ExaminationConnectionManager;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verify examination registry CRUD and session-context behavior.
 */
class ExaminationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_examination_registry_entry(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('examinations.store'), [
            'bcs_number' => 47,
            'name' => '47th BCS',
            'slug' => 'bcs-47',
            'database_name' => 'bcs_exam_47',
            'status' => ExaminationStatus::Ready->value,
            'is_enabled' => '1',
        ])->assertRedirect(route('examinations.index'));

        $this->assertDatabaseHas('examinations', [
            'bcs_number' => 47,
            'database_name' => 'bcs_exam_47',
            'status' => 'ready',
        ]);
    }

    public function test_non_admin_cannot_manage_examinations(): void
    {
        $operator = User::factory()->create();

        $this->actingAs($operator)
            ->get(route('examinations.index'))
            ->assertForbidden();
    }

    public function test_authenticated_user_can_select_an_enabled_examination(): void
    {
        $user = User::factory()->create();
        $examination = Examination::factory()->create([
            'status' => ExaminationStatus::Ready,
            'is_enabled' => true,
        ]);

        // Selection behavior is tested independently from the availability of a
        // developer's local MySQL examination database.
        $this->mock(ExaminationConnectionManager::class, function (MockInterface $mock) use ($examination): void {
            $mock->shouldReceive('check')
                ->once()
                ->withArgs(fn (Examination $value): bool => $value->is($examination))
                ->andReturn(ExaminationConnectionHealth::success(
                    $examination->database_name,
                    null,
                ));
        });

        $this->actingAs($user)
            ->post(route('examinations.select', $examination))
            ->assertSessionHasNoErrors()
            ->assertSessionHas(ExaminationContext::SESSION_KEY, $examination->id);
    }

    public function test_disabled_examination_cannot_be_selected(): void
    {
        $user = User::factory()->create();
        $examination = Examination::factory()->create(['is_enabled' => false]);

        $this->actingAs($user)
            ->post(route('examinations.select', $examination))
            ->assertUnprocessable();
    }

    public function test_active_examination_cannot_be_disabled(): void
    {
        $admin = User::factory()->admin()->create();
        $examination = Examination::factory()->create([
            'status' => ExaminationStatus::Ready,
            'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->withSession([ExaminationContext::SESSION_KEY => $examination->id])
            ->from(route('examinations.edit', $examination))
            ->put(route('examinations.update', $examination), [
                'bcs_number' => $examination->bcs_number,
                'name' => $examination->name,
                'slug' => $examination->slug,
                'database_name' => $examination->database_name,
                'status' => $examination->status->value,
                'is_enabled' => '0',
            ])
            ->assertRedirect(route('examinations.edit', $examination))
            ->assertSessionHasErrors('is_enabled');
    }
}
