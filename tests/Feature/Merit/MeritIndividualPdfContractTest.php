<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritIndividualPdfContractTest extends TestCase
{
    public function test_finalized_individual_merit_pdf_mirrors_audit_sections(): void
    {
        $routes = file_get_contents(base_path('routes/merit.php'));
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $report = file_get_contents(app_path('Reports/Pdf/MeritIndividualPdfReport.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/merit-individual.blade.php'));
        $show = file_get_contents(resource_path('views/merit/show.blade.php'));

        $this->assertStringContainsString("name('pdf')", $routes);
        $this->assertStringContainsString('MeritIndividualPdfReport $report', $controller);
        $this->assertStringContainsString("status === 'finalized'", $controller);
        $this->assertStringContainsString('Individual Finalized Merit', $report);
        $this->assertStringContainsString('Finalized Tabulation Ranking Inputs', $pdf);
        $this->assertStringContainsString('Finalized Choice Validation', $pdf);
        $this->assertStringContainsString('Merit Source Authority', $pdf);
        $this->assertStringContainsString('Generated Merit Ranking', $pdf);
        $this->assertStringContainsString('Cadre-wise Merit Positions', $pdf);
        $this->assertStringContainsString("route('merit.pdf'", $show);
    }
}
