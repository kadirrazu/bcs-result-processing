<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c34LongStatusWrapPolishContractTest extends TestCase
{
    public function test_long_historical_choice_status_is_wrap_safe_on_listing_and_detail(): void
    {
        $listing = file_get_contents(
            resource_path('views/choice-optimization/historical-choices-index.blade.php')
        );
        $detail = file_get_contents(
            resource_path('views/choice-optimization/historical-choice-show.blade.php')
        );

        $this->assertStringContainsString('text-wrap text-break d-inline-block', $listing);
        $this->assertStringContainsString('white-space:normal; max-width:100%; line-height:1.2', $listing);
        $this->assertStringContainsString('{{ $row->optimization_status }}', $listing);

        $this->assertStringContainsString('overflow-wrap:anywhere', $detail);
        $this->assertStringContainsString('{{ $choice->optimization_status }}', $detail);
    }
}
