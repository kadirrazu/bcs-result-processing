<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

class TabulationIndividualViewHotfixContractTest extends TestCase
{
    public function test_individual_view_defines_status_variables_before_use_and_listing_uses_canonical_grand_total_presenters(): void
    {
        $show = file_get_contents(resource_path('views/tabulation/show.blade.php'));
        $results = file_get_contents(resource_path('views/tabulation/results.blade.php'));

        $vivaDeclaration = strpos($show, '$vivaStatus = strtoupper');
        $vivaUse = strpos($show, '@if($vivaStatus !== \'\')');
        $prelimDeclaration = strpos($show, '$prelimStatus = strtoupper');
        $prelimUse = strpos($show, '@if($prelimStatus !== \'\')');

        $this->assertNotFalse($vivaDeclaration);
        $this->assertNotFalse($vivaUse);
        $this->assertLessThan($vivaUse, $vivaDeclaration);
        $this->assertNotFalse($prelimDeclaration);
        $this->assertNotFalse($prelimUse);
        $this->assertLessThan($prelimUse, $prelimDeclaration);
        $this->assertStringContainsString('{{ $r->generalGrandTotalDisplay() }}', $results);
        $this->assertStringContainsString('{{ $r->technicalGrandTotalDisplay() }}', $results);
        $this->assertStringNotContainsString("str_replace('TRACK FAILED'", $results);
    }
}
