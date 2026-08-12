<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

class TabulationIndividualDisplayPolishContractTest extends TestCase
{
    public function test_individual_view_uses_category_code_uppercase_statuses_and_pass_fail_colours(): void
    {
        $view = file_get_contents(resource_path('views/tabulation/show.blade.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/tabulation-individual.blade.php'));

        $this->assertStringContainsString('->value.\' - \'.$registration->cadre_category->code()', $view);
        $this->assertStringContainsString('strtoupper', $view);
        $this->assertStringContainsString('bg-green-lt text-green', $view);
        $this->assertStringContainsString('bg-red-lt text-red', $view);

        $this->assertStringContainsString('->value.\' - \'.$registration->cadre_category->code()', $pdf);
        $this->assertStringContainsString('<span class="ok">PASS</span>', $pdf);
        $this->assertStringContainsString('<span class="bad">FAIL</span>', $pdf);
    }
}
