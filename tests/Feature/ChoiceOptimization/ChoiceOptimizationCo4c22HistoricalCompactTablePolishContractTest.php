<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c22HistoricalCompactTablePolishContractTest extends TestCase
{
    public function test_historical_match_table_merges_method_and_status_and_bolds_bcs_context(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/historical-show.blade.php'));
        $detail = file_get_contents(resource_path('views/choice-optimization/historical-match-show.blade.php'));

        $this->assertStringContainsString('<th>Match / Status</th>', $view);
        $this->assertStringNotContainsString('<th>Method</th>', $view);
        $this->assertStringNotContainsString('<th>Status</th>', $view);

        $this->assertStringContainsString('<div><code>{{ $match->match_method }}</code></div>', $view);
        $this->assertStringContainsString('OPERATOR CONFIRMED', $view);

        $this->assertStringContainsString(
            'Current Candidate <strong>(BCS {{ $currentBcsNumber ?: \'—\' }})</strong>',
            $view
        );
        $this->assertStringContainsString(
            'Previous BCS Record <strong>(BCS {{ $source->previous_bcs_number }})</strong>',
            $view
        );

        $this->assertStringContainsString('<strong>(BCS {{ $currentBcsNumber ?: \'—\' }})</strong>', $detail);
        $this->assertStringContainsString('<strong>(BCS {{ $source->previous_bcs_number }})</strong>', $detail);
    }
}
