<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

final class ApplicationShellTest extends TestCase
{
    public function test_application_layout_is_composed_from_editable_partials(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertIsString($layout);
        $this->assertStringContainsString('layouts.partials.topbar', $layout);
        $this->assertStringContainsString('layouts.partials.examination-navigation', $layout);
        $this->assertStringContainsString('layouts.partials.flash-messages', $layout);
        $this->assertStringContainsString('layouts.partials.footer', $layout);
    }

    public function test_examination_menu_is_guarded_by_active_context(): void
    {
        $navigation = file_get_contents(resource_path('views/layouts/partials/examination-navigation.blade.php'));
        $this->assertIsString($navigation);
        $this->assertStringContainsString('@if ($activeExamination)', $navigation);
        $this->assertStringContainsString("config('navigation.examination.items'", $navigation);
        $this->assertStringContainsString('Route::has', $navigation);
    }

    public function test_tabler_pagination_does_not_repeat_result_summary(): void
    {
        $pagination = file_get_contents(resource_path('views/vendor/pagination/tabler.blade.php'));
        $this->assertIsString($pagination);
        $this->assertStringNotContainsString('Showing', $pagination);
        $this->assertStringContainsString('pagination m-0', $pagination);
    }
}
