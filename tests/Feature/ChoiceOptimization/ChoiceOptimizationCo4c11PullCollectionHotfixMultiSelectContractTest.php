<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c11PullCollectionHotfixMultiSelectContractTest extends TestCase
{
    public function test_grouped_historical_rows_use_base_collection_to_avoid_eloquent_get_key_error(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalPullService.php')
        );

        $this->assertStringContainsString('->toBase()', $service);
        $this->assertStringContainsString('->groupBy(', $service);
        $this->assertStringContainsString("->except('__NO_CORE__')", $service);
    }

    public function test_operator_can_select_one_or_many_bcs_sources_and_each_runs_independently(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $show = file_get_contents(resource_path('views/choice-optimization/historical-show.blade.php'));

        $this->assertStringContainsString("'bcs_numbers' => ['required', 'array', 'min:1']", $controller);
        $this->assertStringContainsString("'bcs_numbers.*' =>", $controller);
        $this->assertStringContainsString('foreach ($bcsNumbers as $bcsNumber)', $controller);
        $this->assertStringContainsString('ProcessChoiceOptimizationHistoricalPull::dispatch', $controller);
        $this->assertStringContainsString('already-running source(s) were skipped', $controller);

        $this->assertStringContainsString('historical-source-checkbox', $view);
        $this->assertStringContainsString('historical-select-all', $view);
        $this->assertStringContainsString('Pull / Re-pull Selected', $view);
        $this->assertStringContainsString('name="bcs_numbers[]"', $view);
        $this->assertStringContainsString('name="bcs_numbers[]"', $show);
    }
}
