<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class GeneralCadreWiseAllocationVerificationContractTest extends TestCase
{
    public function test_general_cadre_wise_reporting_uses_frozen_choices_general_merit_and_active_only_semantics(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/CadreSectionReportingController.php'));
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $landing = file_get_contents(resource_path('views/reporting/cadre-section/index.blade.php'));
        $report = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));

        self::assertStringContainsString('public function generalCadres', $service);
        self::assertStringContainsString("whereNotNull('general_merit_position')", $service);
        self::assertStringContainsString("where('input_freeze_id', (int) $a4Run->input_freeze_id)", $service);
        self::assertStringContainsString('applyPublishedOnly', $service);
        self::assertStringContainsString("elseif ($type === 'general-cadre')", $service);
        self::assertStringContainsString("'general-cadre' => $merit->general_merit_position", $service);
        self::assertStringContainsString("['general-cadre', 'technical-cadre']", $service);

        self::assertStringContainsString('public function generalCadre(', $controller);
        self::assertStringContainsString('public function queueGeneralCadrePdf(', $controller);
        self::assertStringContainsString('/verification/general-cadre/{cadreCode}', $routes);
        self::assertStringContainsString('cadre.verification.general-cadre', $routes);
        self::assertStringContainsString('cadre.verification.general-cadre.pdf', $routes);

        self::assertStringContainsString('General Cadre-wise Allocation Verification Reports', $landing);
        $listing = file_get_contents(resource_path('views/reporting/cadre-section/cadre-wise-index.blade.php'));
        self::assertStringContainsString('cadre.verification.general-cadre-wise', $landing);
        self::assertStringContainsString('id="cadre-search"', $listing);
        self::assertStringContainsString('id="cadre-filter"', $listing);
        self::assertStringContainsString('Search by Cadre Code or Abbreviation', $listing);

        self::assertStringContainsString("reportType === 'general-cadre'", $report);
        self::assertStringContainsString('cadre.verification.general-cadre.pdf', $report);
    }
}
