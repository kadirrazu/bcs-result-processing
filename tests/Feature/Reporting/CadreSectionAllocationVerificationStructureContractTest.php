<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class CadreSectionAllocationVerificationStructureContractTest extends TestCase
{
    public function test_allocation_verification_reports_follow_locked_one_to_six_order(): void
    {
        $view = file_get_contents(resource_path('views/reporting/cadre-section/index.blade.php'));

        $items = [
            '1.</strong> Common Merit Position Allocation Report',
            '2.</strong> General Cadre Candidate Allocation Report',
            '3.</strong> Only Technical Cadre Candidate Allocation Report',
            '4.</strong> General Cadre-wise Allocation Verification Reports',
            '5.</strong> Technical Cadre-wise Allocation Verification Reports',
            '6.</strong> Cadre-wise Serial, Merit &amp; Allocation Basis Report',
        ];

        $positions = [];
        foreach ($items as $item) {
            self::assertStringContainsString($item, $view);
            $positions[] = strpos($view, $item);
        }

        self::assertSame($positions, collect($positions)->sort()->values()->all());

        self::assertStringContainsString('id="general-cadre-wise-verification"', $view);
        self::assertStringContainsString('id="technical-cadre-wise-verification"', $view);

        self::assertLessThan(
            strpos($view, '5. Technical Cadre-wise Allocation Verification Reports'),
            strpos($view, '4. General Cadre-wise Allocation Verification Reports')
        );
    }
}
