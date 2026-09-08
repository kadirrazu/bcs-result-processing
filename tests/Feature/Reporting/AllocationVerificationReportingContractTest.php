<?php

namespace Tests\Feature\Reporting;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationVerificationReportingContractTest extends TestCase
{
    #[Test]
    public function verification_reporting_contract_is_identity_free_and_bound_to_authoritative_sources(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $view = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $routes = file_get_contents(base_path('routes/reporting.php'));

        $this->assertStringContainsString('AllocationA4Result::query()', $service);
        $this->assertStringContainsString('currentMeritRunId()', $service);
        $this->assertStringContainsString('MeritCadreRank::query()', $service);
        $this->assertStringContainsString('AllocationInputCandidate::query()', $service);
        $this->assertStringContainsString("where('input_freeze_id'", $service);
        $this->assertStringContainsString("where('cadre_type', 'TT')", $service);
        $this->assertStringContainsString('Historical Cut-off due to', $service);
        $this->assertStringNotContainsString('previous_reg', $view);
        $this->assertStringNotContainsString('birth_date', $view);
        $this->assertStringNotContainsString('candidate_name', $view);
        $this->assertStringContainsString('Total Allocation Eligible', $view);
        $this->assertStringContainsString('Non-Allocated', $view);
        $this->assertStringContainsString('avr-choice-allocated', $view);
        $this->assertStringContainsString('Allocated Cadre</th>', $view);
        $this->assertStringContainsString('Technical Cadre Merits:', $view);
        $this->assertStringContainsString('avr-allocated-cadre', $view);
        $this->assertStringContainsString('avr-remark-cadre', $view);
        $this->assertStringContainsString('avr-remark-bcs', $view);
        $this->assertStringContainsString('bachelor_code', $service);
        $this->assertStringContainsString('technicalCadreMerits', $service);
        $this->assertStringContainsString('Higher Choice Review', $view);
        $this->assertStringContainsString('higherChoiceReview', $service);
        $this->assertStringContainsString("->map(function ($q) use ($abbr, $seatLedgers, $finalCutoffs): array {", $service);
        $this->assertStringNotContainsString("->map(function ($q) use ($abbr, $seatLedgers, $finalCutoffs): string {", $service);
        $this->assertStringContainsString('AllocationInputQueueEntry', $service);
        $this->assertStringContainsString('AllocationA4SeatLedger', $service);
        $this->assertStringContainsString('Merit position was outside the available posts for all allocation-ready choices.', $service);
        $this->assertStringContainsString('avr-allocation-cell', $view);
        $this->assertStringContainsString('avr-merit-separator', $view);
        $this->assertStringContainsString('avr-table th{vertical-align:middle!important;text-align:center!important}', $view);
        $this->assertStringContainsString('avr-review-cadre', $view);
        $this->assertStringContainsString('avr-review-value', $view);
        $this->assertStringNotContainsString('@endforeach@if(!$loop->last); @else. @endif', $view);
        $this->assertStringContainsString("class=\"{{ !$loop->last ? 'mb-1 pb-1 border-bottom' : '' }}\"", $view);
        $this->assertStringContainsString("ucfirst((string) $detail)", $view);
        $this->assertStringContainsString('cadre.verification.technical', $routes);
    }
}
