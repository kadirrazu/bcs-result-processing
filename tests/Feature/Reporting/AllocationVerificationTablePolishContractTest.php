<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class AllocationVerificationTablePolishContractTest extends TestCase
{
    public function test_browser_and_pdf_apply_compact_alignment_wrapping_subject_history_and_unallocated_rules(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $browser = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/allocation-verification.blade.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));
        $style = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table-style.blade.php'));

        self::assertStringContainsString("_verification-table',", $browser);
        self::assertStringContainsString("_verification-table',", $pdf);
        self::assertStringContainsString('avr-middle-left', $table);
        self::assertStringContainsString('collect($row[\'technical_cadre_merits\'])->chunk(2)', $table);
        self::assertStringContainsString('avr-subject-value', $table);
        self::assertStringContainsString('B_SUBJECT:</span>', $table);
        self::assertStringContainsString('PRS:</span>', $table);

        self::assertStringContainsString('$higherChoiceMissedReasons = $allocation', $service);
        self::assertStringContainsString(': [];', $service);
        self::assertStringContainsString('\'historical_allocations\' => $this->historicalAllocations($history)', $service);
        self::assertStringContainsString('historical_recommendations', $service);
        self::assertStringContainsString('avr-history-cadre', $table);

        self::assertStringContainsString('font-weight:700', $style);
        self::assertStringContainsString('padding:6px 5px', $style);
        self::assertStringContainsString('white-space:nowrap', $style);
        self::assertStringContainsString('empty($row[\'higher_choice_missed_reasons\']) ? \'avr-center\'', $table);
    }
}
