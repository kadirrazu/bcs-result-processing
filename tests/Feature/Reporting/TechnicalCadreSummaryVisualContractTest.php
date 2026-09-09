<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class TechnicalCadreSummaryVisualContractTest extends TestCase
{
    public function test_summary_uses_smaller_font_and_colored_sections(): void
    {
        $view = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $summary = file_get_contents(resource_path('views/reporting/allocation-verification/_technical-cadre-summary.blade.php'));
        $pdfView = file_get_contents(resource_path('views/reports/pdf/allocation-verification.blade.php'));
        $pdfSummary = file_get_contents(resource_path('views/reports/pdf/_technical-cadre-summary.blade.php'));

        self::assertStringContainsString('font-size:.82rem', $view);
        self::assertStringContainsString('font-size:7.4pt', $pdfView);

        foreach (['avr-sum-post','avr-sum-eligible','avr-sum-allocated','avr-sum-nonallocated','avr-sum-other','avr-sum-none'] as $class) {
            self::assertStringContainsString($class, $summary);
        }

        foreach (['sum-post','sum-eligible','sum-allocated','sum-nonallocated','sum-other','sum-none'] as $class) {
            self::assertStringContainsString($class, $pdfSummary);
        }

        self::assertStringContainsString('<strong>{{ number_format(', $summary);
        self::assertStringContainsString('<strong>{{ number_format(', $pdfSummary);
    }
}
