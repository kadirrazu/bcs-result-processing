<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritResultsGrandTotalDisplayContractTest extends TestCase
{
    public function test_merit_results_listing_shows_tabulation_grand_totals_before_rank_columns(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $view = file_get_contents(resource_path('views/merit/results.blade.php'));

        $this->assertStringContainsString(
            "leftJoin('tabulation_results as tabulation_lookup'",
            $controller
        );
        $this->assertStringContainsString(
            "'tabulation_lookup.general_grand_total as general_grand_total'",
            $controller
        );
        $this->assertStringContainsString(
            "'tabulation_lookup.technical_grand_total as technical_grand_total'",
            $controller
        );

        // Locate by heading text rather than requiring a specific <th> class,
        // because alignment/presentation classes are allowed to evolve.
        $grandTotalHeader = strpos($view, 'Grand Total (G/T)</th>');
        $commonHeader = strpos($view, 'Common</th>');
        $generalHeader = strpos($view, 'General</th>');
        $technicalHeader = strpos($view, 'Technical</th>');

        $this->assertNotFalse($grandTotalHeader);
        $this->assertNotFalse($commonHeader);
        $this->assertNotFalse($generalHeader);
        $this->assertNotFalse($technicalHeader);

        $this->assertLessThan($commonHeader, $grandTotalHeader);
        $this->assertLessThan($generalHeader, $grandTotalHeader);
        $this->assertLessThan($technicalHeader, $grandTotalHeader);

        $this->assertStringContainsString(
            'number_format((float) $r->general_grand_total, 2)',
            $view
        );
        $this->assertStringContainsString(
            'number_format((float) $r->technical_grand_total, 2)',
            $view
        );
    }
}
