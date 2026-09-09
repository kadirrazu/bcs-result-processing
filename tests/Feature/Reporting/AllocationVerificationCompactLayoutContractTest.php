<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class AllocationVerificationCompactLayoutContractTest extends TestCase
{
    public function test_browser_and_pdf_share_compact_nine_column_verification_contract(): void
    {
        $browser = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $pdf = file_get_contents(resource_path('views/reports/pdf/allocation-verification.blade.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));
        $style = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table-style.blade.php'));

        self::assertStringContainsString("_verification-table',", $browser);
        self::assertStringContainsString("_verification-table',", $pdf);

        foreach ([
            'Merit<br>Position', 'Category &amp;<br>Written Track', 'Merit Details',
            'Bachelor Subject &amp;<br>PRS', 'Choice List', 'Higher Choice<br>Missed Reason',
            'B_SUBJECT:', 'PRS:', 'CAT:', 'TRACK:', ' - Last Merit (', 'colspan="9"',
        ] as $needle) {
            self::assertStringContainsString($needle, $table);
        }

        self::assertStringNotContainsString('Higher Choice Review', $table);
        foreach (['avr-code-gg','avr-code-gt','avr-code-tt','avr-code-t','white-space:nowrap'] as $needle) {
            self::assertStringContainsString($needle, $style);
        }
    }
}
