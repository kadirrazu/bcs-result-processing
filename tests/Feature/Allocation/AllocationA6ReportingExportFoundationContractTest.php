<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA6ReportingExportFoundationContractTest extends TestCase
{
    #[Test]
    public function a6_is_gated_by_latest_finalized_non_stale_a5_bound_to_latest_current_a4(): void
    {
        $gate = file_get_contents(app_path('Services/Allocation/AllocationA6ReadinessService.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $landing = file_get_contents(resource_path('views/allocation/a6/index.blade.php'));

        self::assertStringContainsString('resolveCurrentSource()', $gate);
        self::assertStringContainsString("\$a5->status !== 'finalized'", $gate);
        self::assertStringContainsString('(bool) $a5->is_stale', $gate);
        self::assertStringContainsString('(int) $a5->candidate_failed > 0', $gate);
        self::assertStringContainsString('(int) $a5->capacity_failed > 0', $gate);
        self::assertStringContainsString("where('status', 'a4_complete')", $gate);
        self::assertStringContainsString('(int) $a5->allocation_a4_run_id !== (int) $latestA4->id', $gate);
        self::assertStringContainsString('hash_equals((string) $a5->a4_output_hash, (string) $latestA4->a4_output_hash)', $gate);
        self::assertStringContainsString("Route::get('/a6'", $routes);
        self::assertStringContainsString('BLOCKED / INACTIVE', $landing);
    }

    #[Test]
    public function a6_supports_latest_queued_txt_excel_docx_and_reusable_reporting_contracts(): void
    {
        $export = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $worker = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));
        $docx = file_get_contents(resource_path('views/allocation/a6/docx.blade.php'));
        $reset = file_get_contents(config_path('development-module-reset.php'));

        foreach (['Exam Title: ', 'Report Title: ', 'Generation Time: ', 'TOTAL ALLOCATED = ', 'NO ELIGIBLE CANDIDATE', 'TOTAL = '] as $needle) {
            self::assertStringContainsString($needle, $export);
        }
        self::assertStringContainsString('max(1, min(20, $perLine))', $export);
        self::assertStringContainsString('cadreTxtZip', $export);
        self::assertStringContainsString("['tabulation_eligible','allocated','cadre']", str_replace(' ', '', $export));
        self::assertStringContainsString("orderBy('merit_position')", $export);
        self::assertStringContainsString("'TOTAL_'.\$key", $export);
        self::assertStringContainsString("'[REPORT_GENERATION_TIMESTAMP]'", $export);
        self::assertStringContainsString('timestampedDownloadName', $export);

        self::assertStringContainsString('ProcessAllocationA6Export::dispatch', $controller);
        self::assertStringContainsString('implements ShouldQueue', $worker);
        self::assertStringContainsString('ReportExportFileStore', $worker);

        foreach (['[[110_ADMN]]','[[TOTAL_110_ADMN]]','[[ALL_ALLOCATED]]','[[TOTAL_ALLOCATED]]'] as $placeholder) {
            self::assertStringContainsString($placeholder, $docx);
        }
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
