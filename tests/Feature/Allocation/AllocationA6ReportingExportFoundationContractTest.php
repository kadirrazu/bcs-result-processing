<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA6ReportingExportFoundationContractTest extends TestCase
{
    #[Test]
    public function a6_is_hard_gated_by_current_finalized_one_hundred_percent_pass_a5(): void
    {
        $gate = file_get_contents(app_path('Services/Allocation/AllocationA6ReadinessService.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $landing = file_get_contents(resource_path('views/allocation/a6/index.blade.php'));

        self::assertStringContainsString('inspectStrict()', $gate);
        self::assertStringContainsString("status === 'finalized'", $gate);
        self::assertStringContainsString("status === 'a4_complete'", $gate);
        self::assertStringContainsString('candidate_failed === 0', $gate);
        self::assertStringContainsString('capacity_failed === 0', $gate);
        self::assertStringContainsString("Route::get('/a6'", $routes);
        self::assertStringContainsString('BLOCKED / INACTIVE', $landing);
    }

    #[Test]
    public function a6_supports_locked_txt_excel_and_docx_contracts(): void
    {
        $export = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));
        $docx = file_get_contents(resource_path('views/allocation/a6/docx.blade.php'));
        $reset = file_get_contents(config_path('development-module-reset.php'));

        self::assertStringContainsString('Exam Title: ', $export);
        self::assertStringContainsString('Report Title: ', $export);
        self::assertStringContainsString('Generation Time: ', $export);
        self::assertStringContainsString('max(1, min(20, $perLine))', $export);
        self::assertStringContainsString('cadreTxtZip', $export);
        self::assertStringContainsString("['tabulation_eligible','allocated','cadre']", $export);
        self::assertStringContainsString("orderBy('merit_position')", $export);
        self::assertStringContainsString("'TOTAL_'.$key", $export);
        self::assertStringContainsString("'[REPORT_GENERATION_TIMESTAMP]'", $export);
        self::assertStringContainsString('[[110_ADMN]]', $docx);
        self::assertStringContainsString('[[TOTAL_110_ADMN]]', $docx);
        self::assertStringContainsString('[[ALL_ALLOCATED]]', $docx);
        self::assertStringContainsString('[[TOTAL_ALLOCATED]]', $docx);
        self::assertStringContainsString('allocation_a6_export_audits', $reset);
        self::assertStringContainsString('allocation_a5_candidate_results', $reset);
    }

    #[Test]
    public function a6_reporting_exposes_consolidated_candidate_and_cadre_drill_down(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $detail = file_get_contents(resource_path('views/allocation/a6/candidate-show.blade.php'));
        $cadre = file_get_contents(resource_path('views/allocation/a6/cadre-show.blade.php'));

        self::assertStringContainsString('candidateDetail', $controller);
        self::assertStringContainsString("->orderBy('merit_position')", $controller);
        foreach (['Registration','Preliminary','Written','Viva','Tabulation','Choice Validation / Optimization','Merit','Final Allocation &amp; A5 Validity'] as $label) {
            self::assertStringContainsString($label, $detail);
        }
        self::assertStringContainsString('Merit Position', $cadre);
    }
}
