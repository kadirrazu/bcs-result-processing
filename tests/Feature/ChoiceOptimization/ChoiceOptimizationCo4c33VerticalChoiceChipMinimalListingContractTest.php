<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c33VerticalChoiceChipMinimalListingContractTest extends TestCase
{
    public function test_shared_choice_chip_is_forced_vertical_on_listing_and_detail(): void
    {
        $partial = file_get_contents(
            resource_path('views/choice-optimization/partials/choice-code-lane.blade.php')
        );
        $listing = file_get_contents(
            resource_path('views/choice-optimization/historical-choices-index.blade.php')
        );
        $detail = file_get_contents(
            resource_path('views/choice-optimization/historical-choice-show.blade.php')
        );

        $this->assertStringContainsString('d-inline-flex flex-column', $partial);
        $this->assertStringContainsString('align-items-center', $partial);
        $this->assertStringContainsString('justify-content-center', $partial);
        $this->assertStringContainsString('str_pad((string)($i + 1)', $partial);
        $this->assertStringContainsString('{{ $code }}', $partial);
        $this->assertStringContainsString('{{ $abbr }}', $partial);

        $this->assertStringContainsString('choice-optimization.partials.choice-code-lane', $listing);
        $this->assertStringContainsString('choice-optimization.partials.choice-code-lane', $detail);
    }

    public function test_listing_keeps_no_historical_status_minimal_and_detail_keeps_explanation(): void
    {
        $listing = file_get_contents(
            resource_path('views/choice-optimization/historical-choices-index.blade.php')
        );
        $detail = file_get_contents(
            resource_path('views/choice-optimization/historical-choice-show.blade.php')
        );

        $this->assertStringContainsString('NO PREVIOUS BCS MATCH', $listing);
        $this->assertStringContainsString('NO HISTORICAL DATA', $listing);
        $this->assertStringNotContainsString(
            'Choice remains unchanged because no confirmed historical recommendation is available.',
            $listing
        );

        $this->assertStringContainsString(
            'Choice remained unchanged because no confirmed Previous BCS recommendation was available.',
            $detail
        );
    }
}
