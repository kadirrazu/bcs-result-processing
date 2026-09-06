<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA55ResultDispositionPublicationControlContractTest extends TestCase
{
    public function test_a55_is_a_separate_audited_publication_layer_and_never_releases_allocation_seats(): void
    {
        $migration = file_get_contents(base_path('database/examination-migrations/2026_09_06_140000_create_allocation_result_dispositions.php'));
        $service = file_get_contents(app_path('Services/Allocation/AllocationResultDispositionService.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $view = file_get_contents(resource_path('views/allocation/disposition/index.blade.php'));
        $show = file_get_contents(resource_path('views/allocation/disposition/show.blade.php'));

        self::assertStringContainsString("create('allocation_result_dispositions'", $migration);
        self::assertStringContainsString("create('allocation_result_disposition_audits'", $migration);
        self::assertStringContainsString("create('allocation_result_disposition_states'", $migration);
        self::assertStringContainsString("public const ACTIVE = 'ACTIVE'", $service);
        self::assertStringContainsString("public const WITHHELD = 'WITHHELD'", $service);
        self::assertStringContainsString("public const CANCELLED = 'CANCELLED'", $service);
        self::assertStringContainsString('A reason is required for every status change.', $service);
        self::assertStringNotContainsString('AllocationA4SeatLedger', $service);
        self::assertStringNotContainsString('AllocationSeatLedger', $service);
        self::assertStringContainsString("Route::get('/a5-5'", $routes);
        self::assertStringContainsString('A5.5 — Result Disposition / Publication Control', $view);
        self::assertStringContainsString('name="cadre_code"', $view);
        self::assertStringContainsString('All Cadres', $view);
        self::assertStringContainsString('text-warning', $view);
        self::assertStringContainsString('No seat release / no reallocation.', $show);

        $controller = file_get_contents(app_path('Http/Controllers/AllocationDispositionController.php'));
        $landing = file_get_contents(resource_path('views/allocation/index.blade.php'));
        self::assertStringContainsString("request->query('cadre_code', 0)", $controller);
        self::assertStringContainsString("where('allocation_a4_results.cadre_code', \$cadreCode)", $controller);
        self::assertStringContainsString('A5 Allocated', $landing);
        self::assertStringContainsString('text-blue', $landing);
        self::assertStringContainsString('text-success', $landing);
        self::assertStringContainsString('text-warning', $landing);
        self::assertStringContainsString('text-danger', $landing);
    }

    public function test_a6_publication_outputs_hard_exclude_withheld_and_cancelled_but_internal_excel_keeps_flags_and_reasons(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationResultDispositionService.php'));
        $export = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));
        $catalog = file_get_contents(app_path('Services/Allocation/AllocationA6ExcelFieldCatalog.php'));
        $cadre = file_get_contents(resource_path('views/allocation/a6/cadre-show.blade.php'));

        self::assertStringContainsString('applyPublishedOnly', $service);
        self::assertStringContainsString("whereIn('ard.status', [self::WITHHELD, self::CANCELLED])", $service);
        self::assertGreaterThanOrEqual(4, substr_count($export, 'applyPublishedOnly'));
        foreach ([
            "'allocation.status'=>'Allocation Status'",
            "'allocation.withheld'=>'Withheld'",
            "'allocation.withheld_reason'=>'Withheld Reason'",
            "'allocation.cancelled'=>'Cancelled'",
            "'allocation.cancelled_reason'=>'Cancelled Reason'",
        ] as $needle) self::assertStringContainsString($needle, $catalog);
        self::assertStringContainsString("\$allocationStatus === 'WITHHELD' ? 'TRUE' : ''", $export);
        self::assertStringContainsString("\$allocationStatus === 'CANCELLED' ? 'TRUE' : ''", $export);
        self::assertStringContainsString('ACTIVE / Publishable', $cadre);
        self::assertStringContainsString('WITHHELD — Internal Review', $cadre);
        self::assertStringContainsString('CANCELLED — Internal Review', $cadre);
    }

    public function test_a55_change_invalidates_old_a6_downloads_without_staling_a3_a4_a5(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));
        $summary = file_get_contents(app_path('Services/Allocation/AllocationA6SummaryService.php'));
        $reset = file_get_contents(config_path('development-module-reset.php'));

        self::assertStringContainsString("'disposition_revision' => \$disposition['revision']", $controller);
        self::assertStringContainsString("'disposition_hash' => \$disposition['hash']", $controller);
        self::assertStringContainsString('This A6 export is OUTDATED because A5.5 publication status changed.', $controller);
        self::assertStringContainsString('A5.5 publication disposition changed after export was queued.', $job);
        self::assertStringContainsString("'withheld_count'", $summary);
        self::assertStringContainsString("'cancelled_count'", $summary);
        self::assertStringContainsString("'published_active'", $summary);
        self::assertStringContainsString("'allocation_result_disposition_audits'", $reset);
    }
    public function test_a55_is_bound_to_exact_current_a5_and_new_allocation_does_not_carry_old_dispositions_forward(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationResultDispositionService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationDispositionController.php'));
        $readiness = file_get_contents(app_path('Services/Allocation/AllocationA6ReadinessService.php'));
        $stale = file_get_contents(app_path('Services/Allocation/AllocationRunStaleService.php'));

        // Disposition state is always scoped by the exact A5 run. Default ACTIVE
        // is implicit, so a newly-current A5 cannot inherit old WITHHELD/CANCELLED rows.
        self::assertStringContainsString("['allocation_a5_run_id' => (int) \$a5->id]", $service);
        self::assertStringContainsString("->where('allocation_a5_run_id', \$a5->id)", $service);
        self::assertStringContainsString('?: self::ACTIVE', $service);

        // A5.5 entry points consume the current A6/A5 authority rather than a
        // historical A5 selected from old disposition data.
        self::assertGreaterThanOrEqual(1, substr_count($controller, '$readiness->requireReady()'));
        self::assertStringContainsString('A5 is stale and must be re-run/finalized.', $readiness);
        self::assertStringContainsString('A5 is not bound to the latest current A4 Allocation result.', $readiness);

        // Latest currentness lifecycle: new A4 invalidates older A5; a successful
        // A5 re-run becomes the single current A5 authority. Historical
        // disposition evidence remains bound to its original A5 run only.
        self::assertStringContainsString('staleA5ForNewA4', $stale);
        self::assertStringContainsString('supersedeEarlierA5ForNewA5', $stale);
        self::assertStringContainsString('repairFalsePositiveA5ForCurrentA4', $stale);
    }

}
