<?php

namespace Tests\Feature\UserManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAppearanceSettingsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_save_and_reset_personal_ui_color_scheme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.appearance.edit'))
            ->assertOk()->assertSee('UI Color Scheme')->assertSee('Soft Blue')->assertSee('Warm Sand')->assertSee('Dusty Rose')->assertSee('Lavender Slate')->assertSee('Reset to Default');

        $this->actingAs($user)->put(route('settings.appearance.update'), ['ui_color_scheme' => 'mist-teal'])
            ->assertRedirect();
        $this->assertSame('mist-teal', $user->fresh()->ui_color_scheme);

        $this->actingAs($user)->delete(route('settings.appearance.reset'))->assertRedirect();
        $this->assertNull($user->fresh()->ui_color_scheme);
    }

    public function test_invalid_scheme_is_rejected_and_scheme_is_ui_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('settings.appearance.update'), ['ui_color_scheme' => 'aggressive-red'])
            ->assertSessionHasErrors('ui_color_scheme');

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('Per-user UI color schemes', $css);
        $this->assertStringContainsString('@media print', $css);
        $this->assertFileDoesNotExist(database_path('examination-migrations/2026_09_28_231500_add_ui_color_scheme_to_users_table.php'));
    }
}
