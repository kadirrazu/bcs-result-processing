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
        $this->assertStringContainsString("whereIn('written_qualified_track', ['TT', 'T'])", $service);
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
        $this->assertStringContainsString('AllocationResultDispositionService', $service);
        $this->assertStringContainsString('applyPublishedOnly', $service);
        $this->assertStringContainsString("'merit_results.registration_id'", $service);
        $this->assertStringContainsString("'merit_cadre_ranks.registration_id'", $service);
        $this->assertStringContainsString("'allocation_a4_results.registration_id'", $service);
        $this->assertStringContainsString('Merit position was outside the available posts for all allocation-ready choices.', $service);
        $this->assertStringContainsString('avr-allocation-cell', $view);
        $this->assertStringContainsString('avr-merit-separator', $view);
        $this->assertStringContainsString('avr-table th{vertical-align:middle!important;text-align:center!important}', $view);
        $this->assertStringContainsString('avr-review-cadre', $view);
        $this->assertStringContainsString('avr-review-value', $view);
        $this->assertStringNotContainsString('@endforeach@if(!$loop->last); @else. @endif', $view);
        $this->assertStringContainsString("class=\"{{ !$loop->last ? 'mb-1 pb-1 border-bottom' : '' }}\"", $view);
        $this->assertStringContainsString("ucfirst((string) $detail)", $view);
        $this->assertStringContainsString("value('cadre_name')", $service);
        $this->assertStringContainsString("value('post_name')", $service);
        $this->assertStringNotContainsString("value('cadre_title')", $service);
        $this->assertStringContainsString('allocation_basis', $service);
        $this->assertStringContainsString('quotaLabels', $service);
        $this->assertStringContainsString('input_choice_codes', $service);
        $this->assertStringContainsString('historicalCutoff', $service);
        $this->assertStringContainsString("value('sanctioned_posts')", $service);
        $this->assertStringContainsString("'total_post' => $totalPost", $service);
        $this->assertStringContainsString('Total Post:', $view);
        $this->assertStringContainsString('>Choice List</th>', $view);
        $this->assertStringContainsString('Validated Choice', $view);
        $this->assertStringContainsString('Allocation-ready Choice', $view);
        $this->assertStringContainsString("->chunk(5)", $view);
        $this->assertStringContainsString('avr-choice-line', $view);
        $this->assertStringContainsString("white-space:nowrap", $view);
        $this->assertStringContainsString("color:#206bc4;font-weight:700", $view);
        $this->assertStringContainsString('avr-basis-mq', $view);
        $this->assertStringContainsString('avr-basis-quota', $view);
        $this->assertStringContainsString('Non Quota', $view);
        $this->assertStringContainsString('cadre.verification.technical', $routes);
    }
}
