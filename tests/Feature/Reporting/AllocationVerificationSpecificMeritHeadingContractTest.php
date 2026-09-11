<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class AllocationVerificationSpecificMeritHeadingContractTest extends TestCase
{
    public function test_verification_reports_use_report_specific_merit_heading_and_separate_sl_column(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));
        $style = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table-style.blade.php'));

        self::assertStringContainsString("'common' => ['COMMON MERIT', 'POSITION']", $service);
        self::assertStringContainsString("'general', 'general-cadre' => ['GENERAL MERIT', 'POSITION']", $service);
        self::assertStringContainsString("'technical-only' => ['TECHNICAL MERIT', 'POSITION']", $service);
        self::assertStringContainsString("'technical-cadre' => [strtoupper", $service);
        self::assertStringContainsString("'quota' => ['APPLICABLE MERIT', 'POSITION']", $service);
        self::assertStringContainsString("'meritHeadingLines'", $service);

        self::assertStringContainsString('class="avr-sl-col">Sl.</th>', $table);
        self::assertStringContainsString('{{ $loop->iteration }}', $table);
        self::assertStringContainsString('$meritHeadingLines', $table);
        self::assertStringContainsString('$columnCount', $table);
        self::assertStringContainsString('.avr-merit-heading{white-space:nowrap', $style);
    }
}
