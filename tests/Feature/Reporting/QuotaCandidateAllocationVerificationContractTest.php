<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class QuotaCandidateAllocationVerificationContractTest extends TestCase
{
    public function test_quota_candidate_report_uses_authoritative_entitlement_frozen_population_filters_and_pdf(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/CadreSectionReportingController.php'));
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $view = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/allocation-verification.blade.php'));
        $summary = file_get_contents(resource_path('views/reporting/allocation-verification/_quota-summary.blade.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));

        self::assertStringContainsString("elseif ($type === 'quota')", $service);
        self::assertStringContainsString("has_ff_quota', 2", $service);
        self::assertStringContainsString("has_em_quota', 1", $service);
        self::assertStringContainsString("has_phc_quota', 1", $service);
        self::assertStringContainsString("where('input_freeze_id', (int) $a4Run->input_freeze_id)", $service);
        self::assertStringContainsString("'registrations.id'", $service);
        self::assertStringContainsString("'allocated_mq'", $service);
        self::assertStringContainsString("'allocated_cff'", $service);
        self::assertStringContainsString("'allocated_em'", $service);
        self::assertStringContainsString("'allocated_phc'", $service);
        self::assertStringContainsString("'not_allocated'", $service);

        self::assertStringContainsString("'quota']", $controller);
        self::assertStringContainsString("'quota']", $routes);

        self::assertStringContainsString('id="quota-filter"', $view);
        self::assertStringContainsString('id="quota-outcome-filter"', $view);
        self::assertStringContainsString('id="quota-cadre-filter"', $view);
        self::assertStringContainsString('data-quotas=', $view);
        self::assertStringContainsString('data-outcome=', $view);
        self::assertStringContainsString('Outcome / Remarks', $table);

        self::assertStringContainsString('Total Quota', $summary);
        self::assertStringContainsString('Allocated by MQ', $summary);
        self::assertStringContainsString('Not Allocated', $summary);

        self::assertStringContainsString("reports.pdf._quota-summary", $pdf);
        self::assertStringContainsString("_verification-table',", $pdf);
        self::assertStringContainsString('Outcome / Remarks', $table);
    }
}
