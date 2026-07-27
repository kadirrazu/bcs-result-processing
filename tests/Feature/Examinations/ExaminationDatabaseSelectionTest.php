<?php

namespace Tests\Feature\Examinations;

use App\Data\ExaminationConnectionHealth;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\User;
use App\Support\Examinations\ExaminationConnectionManager;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class ExaminationDatabaseSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reachable_database_can_be_selected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $examination = Examination::factory()->create();

        $this->mock(ExaminationConnectionManager::class, function (MockInterface $mock) use ($examination): void {
            $mock->shouldReceive('check')->once()->withArgs(fn ($value): bool => $value->is($examination))
                ->andReturn(ExaminationConnectionHealth::success($examination->database_name, null));
        });

        $this->actingAs($user)
            ->post(route('examinations.select', $examination))
            ->assertSessionHasNoErrors();

        $this->assertSame($examination->id, session(ExaminationContext::SESSION_KEY));
    }

    public function test_an_unreachable_database_does_not_replace_the_existing_context(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $existing = Examination::factory()->create();
        $unreachable = Examination::factory()->create();

        session([ExaminationContext::SESSION_KEY => $existing->id]);

        $this->mock(ExaminationConnectionManager::class, function (MockInterface $mock) use ($unreachable): void {
            $mock->shouldReceive('check')->once()->withArgs(fn ($value): bool => $value->is($unreachable))
                ->andReturn(new ExaminationConnectionHealth(false, $unreachable->database_name, null, 'Connection failed'));
        });

        $this->actingAs($user)
            ->post(route('examinations.select', $unreachable))
            ->assertSessionHasErrors('database');

        $this->assertSame($existing->id, session(ExaminationContext::SESSION_KEY));
    }
}
