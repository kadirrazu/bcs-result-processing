<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo312SingleLineChoicePolishContractTest extends TestCase
{
    public function test_batch_and_detail_views_keep_choice_sequences_on_one_horizontal_lane(): void
    {
        $batch = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));
        $detail = file_get_contents(resource_path('views/choice-optimization/omr-row-detail.blade.php'));

        $this->assertStringContainsString('flex-wrap:nowrap', $batch);
        $this->assertStringContainsString('overflow-x:auto', $batch);
        $this->assertStringContainsString('min-width:54px', $batch);

        $this->assertStringContainsString('co-detail-choice-lane', $detail);
        $this->assertStringContainsString('flex-wrap:nowrap', $detail);
        $this->assertStringContainsString('overflow-x:auto', $detail);
        $this->assertStringContainsString('co-detail-stage-meta', $detail);
        $this->assertStringContainsString('<div class="text-secondary small mt-1">{{ $meaning }}</div>', $detail);
        $this->assertStringNotContainsString('<th>Meaning</th>', $detail);
    }
}
