<?php

namespace Tests\Feature\Circular;

use Tests\TestCase;

final class CircularC4ContractTest extends TestCase
{
    public function test_listing_detail_and_eligibility_viewer_contract_is_present(): void
    {
        $routes = file_get_contents(base_path('routes/circular.php'));
        $this->assertStringContainsString("->name('entries.index')", $routes);
        $this->assertStringContainsString("->name('entries.show')", $routes);

        $controller = file_get_contents(app_path('Http/Controllers/CircularController.php'));
        $this->assertStringContainsString('public function entries(Request $request): View', $controller);
        $this->assertStringContainsString("whereHas('bachelorSubjects'", $controller);
        $this->assertStringContainsString("whereHas('prsSubjects'", $controller);
        $this->assertStringContainsString('public function show(CircularEntry $entry): View', $controller);
        $this->assertStringContainsString('Current Master Reference', file_get_contents(resource_path('views/circular/entry-show.blade.php')));
        $this->assertStringContainsString('Eligibility Viewer', file_get_contents(resource_path('views/circular/entry-show.blade.php')));
        $this->assertStringContainsString('Historical version', file_get_contents(resource_path('views/circular/entries-index.blade.php')));
    }
}
