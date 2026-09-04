<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AllocationA5UiReportingPolishContractTest extends TestCase
{
    #[Test]
    public function a5_summary_is_cadre_first_and_candidate_report_is_separate(): void
    {
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationController.php'));
        $summary = file_get_contents(resource_path('views/allocation/a5-show.blade.php'));
        $candidates = file_get_contents(resource_path('views/allocation/a5-candidates.blade.php'));
        $cadre = file_get_contents(resource_path('views/allocation/a5-cadre-results.blade.php'));

        $this->assertStringContainsString("name('a5.candidates')", $routes);
        $this->assertStringContainsString("name('a5.cadre-results')", $routes);
        $this->assertStringContainsString('showA5Candidates', $controller);
        $this->assertStringContainsString('showA5CadreResults', $controller);

        $this->assertStringContainsString('A5 - Allocated Candidate & Final Cadre Seat-Limit Validation', $summary);
        $this->assertStringContainsString('Candidate Validity Report', $summary);
        $this->assertStringContainsString('Source Data Version', $summary);
        $this->assertStringContainsString('Validation Version', $summary);
        $this->assertStringContainsString('General Cadre', $summary);
        $this->assertStringContainsString('Technical / Professional Cadre', $summary);
        $this->assertStringContainsString('Candidate Validation', $summary);
        $this->assertStringContainsString('Seat Limit Validation', $summary);
        $this->assertStringContainsString("route('allocation.a5.cadre-results'", $summary);

        $this->assertStringContainsString('PRE-FINALIZED VALIDATION REPORT', $candidates);
        $this->assertStringContainsString('POST-FINALIZED / FINALIZED VALIDATION REPORT', $candidates);
        $this->assertStringContainsString('Reg / Cadre Code / Abbreviation', $candidates);
        $this->assertStringContainsString('Cadre Filter', $candidates);
        $this->assertStringContainsString('<th>SL</th>', $candidates);
        $this->assertStringContainsString('firstItem()+$loop->index', $candidates);
        $this->assertStringContainsString('$row->cadre_code }} - {{ $abbr', $candidates);

        $this->assertStringContainsString('A5 Candidate Validation', $cadre);
        $this->assertStringContainsString('Exact cadre drill-down', $cadre);
    }
}
