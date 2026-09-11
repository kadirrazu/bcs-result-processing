<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class BookletPrintingReportsContractTest extends TestCase
{
    public function test_booklet_reports_reuse_authority_include_identity_omit_higher_choice_and_exclude_serial_merit_family(): void
    {
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Reporting/CadreSectionReportingController.php'));
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $landing = file_get_contents(resource_path('views/reporting/cadre-section/index.blade.php'));
        $report = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationVerificationPdfExport.php'));

        self::assertStringContainsString("cadre.booklet", $routes);
        self::assertStringContainsString("bookletGeneralCadreWiseIndex", $controller);
        self::assertStringContainsString("bookletTechnicalCadreWiseIndex", $controller);
        self::assertStringContainsString("buildBooklet", $service);
        self::assertStringContainsString("true, false", $service);

        foreach (['reg', 'name', 'father_name', 'birth_date'] as $identityField) {
            self::assertStringContainsString("'{$identityField}'", $service);
        }

        self::assertStringContainsString('Candidate<br>Information', $table);
        self::assertStringContainsString('candidate_reg', $table);
        self::assertStringContainsString('candidate_name', $table);
        self::assertStringContainsString('candidate_father', $table);
        self::assertStringContainsString('candidate_dob', $table);
        self::assertStringContainsString('$showHigherChoice = !$booklet', $table);

        self::assertStringContainsString('id="candidate-filter"', $report);
        self::assertStringContainsString('id="merit-filter"', $report);
        self::assertStringContainsString('Allocated Cadre', $report);

        self::assertStringContainsString("Booklet Printing Reports", $landing);
        self::assertStringNotContainsString(
            "route('examination-reports.cadre.booklet.cadre-serial-merit",
            $landing
        );

        self::assertStringContainsString("'cadre_booklet'", $job);
        self::assertStringContainsString('buildBooklet', $job);
    }
}
