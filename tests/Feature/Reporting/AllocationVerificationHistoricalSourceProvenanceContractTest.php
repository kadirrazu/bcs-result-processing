<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class AllocationVerificationHistoricalSourceProvenanceContractTest extends TestCase
{
    public function test_historical_remarks_preserve_archive_and_google_source_provenance(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));

        self::assertStringContainsString("'previous_bcs_repository' => 'Archive'", $service);
        self::assertStringContainsString("'google_form' => 'Google'", $service);
        self::assertStringContainsString("'sources' => array_values($sources)", $service);
        self::assertStringContainsString("implode(', ', \$historyCadre['sources'])", $table);
        self::assertStringContainsString("\$historyCadre['cadre']", $table);
    }
}
