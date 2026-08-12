<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

class TabulationIndividualEqualHeightPdfLayoutContractTest extends TestCase
{
    public function test_individual_view_and_pdf_use_matching_two_by_two_information_layout(): void
    {
        $show = file_get_contents(resource_path('views/tabulation/show.blade.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/tabulation-individual.blade.php'));

        $upstream = substr($show, 0, strpos($show, 'Source → Derived Verification'));

        $this->assertGreaterThanOrEqual(4, substr_count($upstream, 'card w-100 h-100'));
        $this->assertStringContainsString('col-6 text-secondary', $upstream);
        $this->assertStringContainsString('col-6 fw-medium', $upstream);

        $this->assertStringContainsString('class="upstream-grid"', $pdf);
        $this->assertGreaterThanOrEqual(4, substr_count($pdf, 'class="info-card"'));
        $this->assertStringContainsString('class="info-label"', $pdf);
        $this->assertStringContainsString('class="info-value"', $pdf);
    }
}
