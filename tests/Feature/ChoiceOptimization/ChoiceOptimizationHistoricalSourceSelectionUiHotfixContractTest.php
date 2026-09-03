<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationHistoricalSourceSelectionUiHotfixContractTest extends TestCase
{
    public function test_historical_source_selection_uses_one_shared_checkbox_and_explicit_only_selected_action(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));

        $this->assertStringContainsString('id="historical-select-all"', $view);
        $this->assertStringContainsString('id="historical-deselect-all"', $view);
        $this->assertStringContainsString('id="historical-use-only-selected"', $view);
        $this->assertStringContainsString('value="only"', $view);
        $this->assertStringContainsString('data-source-id="{{ $source?->id }}"', $view);
        $this->assertStringContainsString("hidden.name = 'source_ids[]'", $view);
        $this->assertStringNotContainsString('historical-usage-checkbox', $view);

        $this->assertStringContainsString("'action' => ['required', 'in:include,exclude,only']", $controller);
        $this->assertStringContainsString("\$validated['action'] === 'only'", $controller);
        $this->assertStringContainsString('HISTORICAL_SOURCE_SELECTION_REPLACED', $controller);
        $this->assertStringContainsString('complete Previous-BCS optimization source set', $controller);
    }
}
