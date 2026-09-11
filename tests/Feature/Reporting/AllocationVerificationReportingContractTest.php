<?php

namespace Tests\Feature\Reporting;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationVerificationReportingContractTest extends TestCase
{
    #[Test]
    public function verification_reporting_contract_is_identity_free_compact_and_bound_to_authoritative_sources(): void
    {
        $service = file_get_contents(app_path('Services/Reporting/AllocationVerificationReportService.php'));
        $view = file_get_contents(resource_path('views/reporting/allocation-verification/report.blade.php'));
        $routes = file_get_contents(base_path('routes/reporting.php'));
        $table = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table.blade.php'));
        $style = file_get_contents(resource_path('views/reporting/allocation-verification/_verification-table-style.blade.php'));

        $this->assertStringContainsString('AllocationA4Result::query()', $service);
        $this->assertStringContainsString('currentMeritRunId()', $service);
        $this->assertStringContainsString('MeritCadreRank::query()', $service);
        $this->assertStringContainsString('AllocationInputCandidate::query()', $service);
        $this->assertStringContainsString("where('input_freeze_id'", $service);
        $this->assertStringContainsString("where('cadre_type', 'TT')", $service);
        $this->assertStringContainsString("whereIn('written_qualified_track', ['TT', 'T'])", $service);
        $this->assertStringContainsString('Historical Cut-off due to', $table);

        $this->assertStringNotContainsString('previous_reg', $table);
        $this->assertStringContainsString('@if($booklet)', $table);
        $this->assertStringContainsString('candidate_name', $table);
        $this->assertStringContainsString('Candidate<br>Information', $table);

        $this->assertStringContainsString('avr-merit-heading', $table);
        $this->assertStringContainsString('Category &amp;<br>Written Track', $table);
        $this->assertStringContainsString('Merit Details', $table);
        $this->assertStringContainsString('Bachelor Subject &amp;<br>PRS', $table);
        $this->assertStringContainsString('Higher Choice<br>Missed Reason', $table);
        $this->assertStringNotContainsString('Higher Choice Review', $table);

        $this->assertStringContainsString('CAT:', $table);
        $this->assertStringContainsString('TRACK:', $table);
        $this->assertStringContainsString('B_SUBJECT:', $table);
        $this->assertStringContainsString('PRS:', $table);
        $this->assertStringContainsString(' - Last Merit (', $table);
        $this->assertStringContainsString("'last_merits' =>", $service);
        $this->assertStringContainsString('higherChoiceMissedReasons', $service);
        $this->assertStringNotContainsString('higherChoiceReview', $service);

        $this->assertStringContainsString('AllocationInputQueueEntry', $service);
        $this->assertStringContainsString('AllocationResultDispositionService', $service);
        $this->assertStringContainsString('applyPublishedOnly', $service);
        $this->assertStringContainsString("'merit_results.registration_id'", $service);
        $this->assertStringContainsString("'merit_cadre_ranks.registration_id'", $service);
        $this->assertStringContainsString("'allocation_a4_results.registration_id'", $service);

        $this->assertStringContainsString('avr-allocation-cell', $table);
        $this->assertStringContainsString('avr-merit-separator', $table);
        $this->assertStringContainsString('avr-review-cadre', $table);
        $this->assertStringContainsString('avr-review-value', $table);
        $this->assertStringNotContainsString('@endforeach@if(!$loop->last); @else. @endif', $table);

        $this->assertStringContainsString("value('cadre_name')", $service);
        $this->assertStringContainsString("value('post_name')", $service);
        $this->assertStringNotContainsString("value('cadre_title')", $service);
        $this->assertStringContainsString('allocation_basis', $service);
        $this->assertStringContainsString('quotaLabels', $service);
        $this->assertStringContainsString('input_choice_codes', $service);
        $this->assertStringContainsString('historicalCutoff', $service);
        $this->assertStringContainsString("value('sanctioned_posts')", $service);
        $this->assertStringContainsString("'total_post' => $totalPost", $service);

        $this->assertStringContainsString('>Choice List</th>', $table);
        $this->assertStringContainsString('Validated Choice', $table);
        $this->assertStringContainsString('Allocation-ready Choice', $table);
        $this->assertStringContainsString("->chunk(5)", $table);
        $this->assertStringContainsString('avr-choice-line', $table);
        $this->assertStringContainsString('white-space:nowrap', $style);
        $this->assertStringContainsString('avr-basis-mq', $table);
        $this->assertStringContainsString('avr-basis-quota', $table);
        $this->assertStringContainsString('Non Quota', $table);
        $this->assertStringContainsString('cadre.verification.technical', $routes);
    }
}
