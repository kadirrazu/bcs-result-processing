<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class TechnicalMeritPopulationAllocationReportsContractTest extends TestCase
{
    public function test_general_technical_and_only_technical_reports_use_locked_merit_populations_and_titles(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/CadreSectionReportingController.php'));
        $landing = file_get_contents(resource_path('views/reporting/cadre-section/index.blade.php'));

        self::assertStringContainsString('General Cadre Candidate Allocation Report (GG + GN + GT)', $service);
        self::assertStringContainsString('Technical Cadre Candidate Allocation Report (TT + T + GT)', $service);
        self::assertStringContainsString('Only Technical Cadre Candidate Allocation Report (TT + T)', $service);

        self::assertStringContainsString("elseif (\$type === 'technical')", $service);
        self::assertStringContainsString("->whereNotNull('technical_merit_position')", $service);
        self::assertStringContainsString("->whereIn('written_qualified_track', ['TT', 'T'])", $service);
        self::assertStringContainsString("'technical', 'technical-only' => \$merit->technical_merit_position", $service);
        self::assertStringContainsString("'technical', 'technical-only' => ['TECHNICAL MERIT', 'POSITION']", $service);

        self::assertStringContainsString("['common','general','technical','technical-only','quota']", $routes);
        self::assertStringContainsString("['common', 'general', 'technical', 'technical-only', 'quota']", $controller);
        self::assertStringContainsString("['type'=>'technical']", $landing);
        self::assertSame(2, substr_count($landing, 'Technical Cadre Candidate Allocation Report (TT + T + GT)'));
        self::assertSame(2, substr_count($landing, 'Only Technical Cadre Candidate Allocation Report (TT + T)'));
    }
}
