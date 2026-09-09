<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class CadreSectionAllocationVerificationStructureContractTest extends TestCase
{
    public function test_allocation_verification_reports_follow_modern_locked_one_to_seven_order(): void
    {
        $view = file_get_contents(resource_path('views/reporting/cadre-section/index.blade.php'));

        $items = [
            'Common Merit Position Allocation Report',
            'General Cadre Candidate Allocation Report',
            'Only Technical Cadre Candidate Allocation Report',
            'Quota Candidate Allocation Verification Report',
            'General Cadre-wise Allocation Verification Reports',
            'Technical Cadre-wise Allocation Verification Reports',
            'Cadre-wise Serial, Merit &amp; Allocation Basis Report',
        ];

        $positions = [];
        foreach ($items as $item) {
            self::assertStringContainsString($item, $view);
            $positions[] = strpos($view, $item);
        }

        self::assertSame($positions, collect($positions)->sort()->values()->all());
        self::assertStringContainsString('csr-grid', $view);
        self::assertStringContainsString('grid-template-columns:repeat(2,minmax(0,1fr))', $view);
        self::assertStringContainsString('csr-tile', $view);
        self::assertStringNotContainsString('list-group list-group-flush', $view);
        self::assertStringContainsString("['type'=>'quota']", $view);
    }
}
