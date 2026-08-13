<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

final class TabulationReviewReportingPhaseContractTest extends TestCase
{
    public function test_review_reporting_phase_contract_is_present(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TabulationController.php'));
        $results = file_get_contents(resource_path('views/tabulation/results.blade.php'));
        $show = file_get_contents(resource_path('views/tabulation/show.blade.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/tabulation-individual.blade.php'));
        $summaryService = file_get_contents(app_path('Services/Tabulation/TabulationReviewSummaryService.php'));

        $this->assertStringContainsString('TabulationReviewSummaryService', $controller);
        $this->assertStringContainsString('registration_lookup.name as candidate_name', $controller);
        $this->assertStringContainsString("whereJsonContains('tabulation_results.review_warnings'", $controller);
        $this->assertStringContainsString('Source → Derived Verification', $show);
        $this->assertStringContainsString('Source → Derived Verification', $pdf);
        $this->assertStringContainsString('Tabulated Population', $results);
        $this->assertStringContainsString('Merit Eligibility Outcome', $results);
        $this->assertStringContainsString('Name, REG or USER', $results);
        $this->assertStringContainsString('general_only_merit_eligible', $summaryService);
        $this->assertStringContainsString('technical_high_warning', $summaryService);
        $this->assertStringContainsString("'Validation Errors'", $controller);
        $this->assertStringContainsString("'Source Viva Result'", $controller);
    }
}
