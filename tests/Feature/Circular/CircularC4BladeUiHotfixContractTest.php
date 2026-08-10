<?php

namespace Tests\Feature\Circular;

use Tests\TestCase;

class CircularC4BladeUiHotfixContractTest extends TestCase
{
    public function test_circular_landing_view_has_balanced_control_directives_contract(): void
    {
        $view = file_get_contents(resource_path('views/circular/index.blade.php'));

        $this->assertSame(substr_count($view, '@if'), substr_count($view, '@endif'));
        $this->assertSame(substr_count($view, '@foreach'), substr_count($view, '@endforeach'));
        $this->assertStringNotContainsString('@endif@if', $view);
        $this->assertStringContainsString('Approve Current Draft as Effective', $view);
    }

    public function test_real_circular_view_spaces_details_and_edit_actions(): void
    {
        $view = file_get_contents(resource_path('views/circular/view.blade.php'));

        $this->assertStringContainsString('d-inline-flex align-items-center gap-2', $view);
        $this->assertStringContainsString('>Details</a>', $view);
        $this->assertStringContainsString('>Edit</a>', $view);
    }
}
