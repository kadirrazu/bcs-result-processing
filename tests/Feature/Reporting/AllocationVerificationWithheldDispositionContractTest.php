<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class AllocationVerificationWithheldDispositionContractTest extends TestCase
{
    public function test_verification_includes_withheld_excludes_cancelled_while_booklet_remains_active_only(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));
        $style = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table-style.blade.php'));

        self::assertStringContainsString('applyReportDisposition', $service);
        self::assertStringContainsString('AllocationResultDispositionService::CANCELLED', $service);
        self::assertStringContainsString('return $this->dispositions->applyPublishedOnly', $service);
        self::assertStringContainsString("'is_withheld'", $service);
        self::assertStringContainsString("'WITHHELD'.($dispositionReason", $service);

        self::assertStringContainsString('avr-withheld', $table);
        self::assertStringContainsString('(WITHHELD)', $table);
        self::assertStringContainsString('color:#d63939', $style);
    }
}
