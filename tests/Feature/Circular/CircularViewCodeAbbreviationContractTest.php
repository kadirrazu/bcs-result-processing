<?php

namespace Tests\Feature\Circular;

use Tests\TestCase;

final class CircularViewCodeAbbreviationContractTest extends TestCase
{
    public function test_circular_view_displays_master_abbreviation_below_effective_code(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CircularController.php'));
        $view = file_get_contents(resource_path('views/circular/view.blade.php'));

        $this->assertStringContainsString('circularAbbreviationMap($entries)', $controller);
        $this->assertStringContainsString('whereIn(\'cadre_code\', $mainCodes)', $controller);
        $this->assertStringContainsString('whereIn(\'sub_cadre_code\', $subCodes)', $controller);
        $this->assertStringContainsString("'cadre_abbr'", $controller);
        $this->assertStringContainsString("'sub_cadre_abbr'", $controller);
        $this->assertStringContainsString("'abbreviationMap'", $controller);

        $this->assertStringContainsString('{{ $entry->effective_code }}', $view);
        $this->assertStringContainsString('class="text-primary">[{{ $abbreviationMap[(int) $entry->effective_code] }}]</div>', $view);
    }
}
