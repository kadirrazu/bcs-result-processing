<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritReviewReportingPhaseContractTest extends TestCase
{
    public function test_merit_review_reporting_and_finalized_individual_view_contract_is_present(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $results = file_get_contents(resource_path('views/merit/results.blade.php'));
        $show = file_get_contents(resource_path('views/merit/show.blade.php'));
        $routes = file_get_contents(base_path('routes/merit.php'));
        $summary = file_get_contents(app_path('Services/Merit/MeritReviewSummaryService.php'));

        $this->assertStringContainsString('Merit Reconciliation Summary', $results);
        $this->assertStringContainsString('Name, REG or USER', $results);
        $this->assertStringContainsString('HASH_VERIFIED_AT_FINALIZATION', $results);
        $this->assertStringContainsString('Individual Finalized Merit', $show);
        $this->assertStringContainsString("name('show')", $routes);
        $this->assertStringContainsString("name('export.xlsx')", $routes);
        $this->assertStringContainsString("name('cadre.export.xlsx')", $routes);
        $this->assertStringContainsString('AdministrativeXlsxExportService', $controller);
        $this->assertStringContainsString('selectRaw(\'COUNT(*) as total\')', $summary);
        $this->assertStringContainsString("COUNT(DISTINCT cadre_code)", $summary);
    }
}
