<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

final class NonCadreReportingCandidateSearchContractTest extends TestCase
{
    public function test_nc5_candidate_search_and_detail_are_isolated_and_wired(): void
    {
        $routes = file_get_contents(base_path('routes/non-cadre.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/NonCadre/Reporting/NonCadreReportingController.php'));
        $service = file_get_contents(base_path('app/Services/NonCadre/Reporting/NonCadreReportingService.php'));
        $index = file_get_contents(base_path('resources/views/non-cadre/reporting/index.blade.php'));
        $list = file_get_contents(base_path('resources/views/non-cadre/reporting/candidates.blade.php'));
        $detail = file_get_contents(base_path('resources/views/non-cadre/reporting/candidate-show.blade.php'));

        $this->assertStringContainsString("Route::get('/candidates'", $routes);
        $this->assertStringContainsString("Route::get('/candidates/{reg}'", $routes);
        $this->assertStringContainsString('function candidates(', $controller);
        $this->assertStringContainsString('function candidate(', $controller);
        $this->assertStringContainsString('function candidateSearch(', $service);
        $this->assertStringContainsString('function candidateDetail(', $service);
        $this->assertStringContainsString("'r.reg', 'like'", $service);
        $this->assertStringContainsString("'r.user_id', 'like'", $service);
        $this->assertStringContainsString("'r.name', 'like'", $service);
        $this->assertStringContainsString('Candidate Search', $index);
        $this->assertStringContainsString('Search Reg / User ID / Name', $list);
        $this->assertStringContainsString('Non-Cadre Choice Authority', $detail);
        $this->assertStringContainsString('Final Non-Cadre Allocation', $detail);
        $this->assertStringNotContainsString('AllocationA6ReportService', $service);
    }
}
