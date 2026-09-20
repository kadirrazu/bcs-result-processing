<?php
namespace Tests\Feature\NonCadre;
use Tests\TestCase;
final class NonCadreSeatBreakupNc2ContractTest extends TestCase
{
 public function test_nc2_uses_cadre_allocation_quota_config_and_isolated_namespace():void
 { $s=file_get_contents(app_path('Services/NonCadre/SeatBreakup/NonCadreSeatBreakupService.php')); self::assertStringContainsString("config('allocation.provisional_breakup_percentages'",$s); self::assertStringContainsString("config('allocation.quota_breakup_minimum_total_posts'",$s); self::assertStringNotContainsString('AllocationSeatBreakupService',$s); self::assertStringContainsString("['post_grade','post_serial','post_sub_serial','post_code','total_post','merit','cff','em','phc']",$s); }
 public function test_nc2_enforces_seat_conservation_and_locked_low_post_rule():void
 { $s=file_get_contents(app_path('Services/NonCadre/SeatBreakup/NonCadreSeatBreakupService.php')); self::assertStringContainsString('merit+cff+em+phc must equal total_post',$s); self::assertStringContainsString('100% Merit/MQ',$s); self::assertStringContainsString('array_sum($seats)',$s); }
 public function test_nc2_routes_and_landing_are_wired():void
 { $r=file_get_contents(base_path('routes/non-cadre.php')); $v=file_get_contents(resource_path('views/non-cadre/index.blade.php')); self::assertStringContainsString("prefix('seat-breakup')",$r); self::assertStringContainsString("non-cadre.seat-breakup.index",$v); self::assertStringContainsString('Open NC2 — Seat Breakup',$v); }
 public function test_nc2_refinalization_stales_only_downstream_allocation_and_reporting():void
 { $s=file_get_contents(app_path('Services/NonCadre/SeatBreakup/NonCadreSeatBreakupService.php')); self::assertStringContainsString('$state[\'allocation_status\']=\'stale\'',$s); self::assertStringContainsString('$state[\'reporting_status\']=\'stale\'',$s); self::assertStringNotContainsString('$state[\'choice_status\']=\'stale\'',$s); }

    public function test_nc2_version_exposes_pdf_export_contract(): void
    {
        $routes = file_get_contents(base_path('routes/non-cadre.php'));
        $controller = file_get_contents(app_path('Http/Controllers/NonCadre/SeatBreakup/NonCadreSeatBreakupController.php'));
        $view = file_get_contents(resource_path('views/non-cadre/seat-breakup/version.blade.php'));
        $report = file_get_contents(app_path('Reports/Pdf/NonCadre/NonCadreSeatBreakupPdfReport.php'));

        self::assertStringContainsString("name('version.pdf')", $routes);
        self::assertStringContainsString('NonCadreSeatBreakupPdfReport', $controller);
        self::assertStringContainsString('Export PDF', $view);
        self::assertStringContainsString('Non-Cadre Seat Breakup', $report);
        self::assertStringContainsString('Circular Version:', $report);
        self::assertStringContainsString('Seat Breakup Version:', $report);
    }
}
