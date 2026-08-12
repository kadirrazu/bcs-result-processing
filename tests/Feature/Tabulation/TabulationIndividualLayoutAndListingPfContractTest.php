<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

class TabulationIndividualLayoutAndListingPfContractTest extends TestCase
{
    public function test_individual_view_uses_two_by_two_equal_height_cards_and_listing_colours_pf(): void
    {
        $show = file_get_contents(resource_path('views/tabulation/show.blade.php'));
        $results = file_get_contents(resource_path('views/tabulation/results.blade.php'));

        $upstream = substr($show, 0, strpos($show, 'Source → Derived Verification'));

        $this->assertGreaterThanOrEqual(4, substr_count($upstream, 'col-lg-6 d-flex'));
        $this->assertGreaterThanOrEqual(4, substr_count($upstream, 'card w-100 h-100'));
        $this->assertStringContainsString('col-6 text-secondary', $upstream);
        $this->assertStringContainsString('col-6 fw-medium', $upstream);
        $this->assertStringContainsString('Qualified Track', $show);
        $this->assertStringContainsString('General Counted', $show);
        $this->assertStringContainsString('Technical Counted', $show);

        $this->assertStringContainsString(
            'strtoupper((string) $r->general_pf)===\'PASS\'',
            $results
        );
        $this->assertStringContainsString("'success'", $results);
        $this->assertStringContainsString("'danger'", $results);
    }
}
