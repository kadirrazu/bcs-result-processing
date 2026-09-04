<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA6CandidateReportingUiPolishContractTest extends TestCase
{
    public function test_a6_candidate_reporting_polish_contract_is_present(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $service = file_get_contents(app_path('Services/Allocation/AllocationA6ReportService.php'));
        $list = file_get_contents(resource_path('views/allocation/a6/candidates.blade.php'));
        $detail = file_get_contents(resource_path('views/allocation/a6/candidate-show.blade.php'));

        self::assertStringContainsString('$totalCandidates = (clone $baseQuery)->count();', $controller);
        self::assertStringContainsString("'allocationAbbr'", $service);
        self::assertStringContainsString("'registration_reference'", $service);
        self::assertStringContainsString("'choice_reporting'", $service);
        self::assertStringContainsString('ChoiceOptimizationEffectiveChoice', $service);
        self::assertStringContainsString('Previous BCS cutoff applied', $service);

        self::assertStringContainsString('A6 — Reporting &amp; Export - Candidate Search', $list);
        self::assertStringContainsString('Total Candidates', $list);
        self::assertStringContainsString("$allocationAbbr->get", $list);
        self::assertStringContainsString('A6 — Reporting &amp; Export', $list);

        self::assertStringContainsString('ALLOCATED TO', $detail);
        self::assertStringContainsString('Category', $detail);
        self::assertStringContainsString('Sex', $detail);
        self::assertStringContainsString('District', $detail);
        self::assertStringContainsString('NON QUOTA', $detail);
        self::assertStringContainsString('Registration Choice', $detail);
        self::assertStringContainsString('Validated Choice', $detail);
        self::assertStringContainsString('OMR Choice', $detail);
        self::assertStringContainsString('Effective Choice', $detail);
        self::assertStringContainsString('Change Summary', $detail);
        self::assertStringContainsString('Final Allocation &amp; A5 Validity', $detail);
    }
}
