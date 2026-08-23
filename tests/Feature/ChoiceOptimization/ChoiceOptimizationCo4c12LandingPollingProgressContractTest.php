<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c12LandingPollingProgressContractTest extends TestCase
{
    public function test_choice_optimization_landing_polls_running_historical_sources_and_refreshes_once_after_completion(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));

        $this->assertStringContainsString('historical-source-row', $view);
        $this->assertStringContainsString('data-status-url', $view);
        $this->assertStringContainsString('progress-bar-indeterminate', $view);
        $this->assertStringContainsString('Historical matching in progress', $view);

        $this->assertStringContainsString('pollHistoricalSources', $view);
        $this->assertStringContainsString("fetch(url", $view);
        $this->assertStringContainsString("window.setInterval(pollHistoricalSources, 1500)", $view);
        $this->assertStringContainsString('observedRunning && !anyRunning', $view);
        $this->assertStringContainsString('window.location.replace(window.location.href)', $view);
    }
}
