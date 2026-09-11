<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class AllocationReportFilteringContractTest extends TestCase
{
    public function test_verification_and_booklet_have_locked_search_and_cadre_filter_contracts(): void
    {
        $report = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));

        self::assertStringContainsString('Merit Position', $report);
        self::assertStringContainsString('Allocated Cadre', $report);
        self::assertStringContainsString('id="candidate-filter"', $report);
        self::assertStringContainsString('Reg / Name', $report);
        self::assertStringContainsString('data-merit=', $table);
        self::assertStringContainsString('data-cadre=', $table);
        self::assertStringContainsString('data-reg=', $table);
        self::assertStringContainsString('data-name=', $table);
        self::assertStringContainsString("rowMerit===meritQuery", $report);
        self::assertStringContainsString("identity.includes(candidateQuery)", $report);
    }
}
