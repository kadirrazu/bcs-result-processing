<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c23HistoricalRegressionCompatibilityContractTest extends TestCase
{
    public function test_final_historical_ui_preserves_prior_semantics_and_current_dynamic_labels(): void
    {
        $list = file_get_contents(resource_path('views/choice-optimization/historical-show.blade.php'));
        $detail = file_get_contents(resource_path('views/choice-optimization/historical-match-show.blade.php'));

        $this->assertStringContainsString('MATCHED rows have exact primary identity', $list);
        $this->assertStringContainsString(
            'Current Candidate <strong>(BCS {{ $currentBcsNumber ?: \'—\' }})</strong>',
            $list
        );
        $this->assertStringContainsString(
            'Previous BCS Record <strong>(BCS {{ $source->previous_bcs_number }})</strong>',
            $detail
        );
        $this->assertStringContainsString('Confirmed by:', $detail);
    }
}
