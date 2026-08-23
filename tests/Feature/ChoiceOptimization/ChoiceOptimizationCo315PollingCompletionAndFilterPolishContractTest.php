<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo315PollingCompletionAndFilterPolishContractTest extends TestCase
{
    public function test_processing_completion_refreshes_once_and_warning_operator_filters_are_functional(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));

        $this->assertStringContainsString('$status === \'warning\'', $controller);
        $this->assertStringContainsString('JSON_LENGTH(COALESCE(validation_warnings, JSON_ARRAY())) > 0', $controller);
        $this->assertStringContainsString('$status === \'operator_confirmed\'', $controller);
        $this->assertStringContainsString("whereNotNull('decision_resolved_at')", $controller);
        $this->assertStringContainsString("orWhereNotNull('resolved_at')", $controller);

        $this->assertStringContainsString("'warning'=>'Warning'", $view);
        $this->assertStringContainsString("'operator_confirmed'=>'Operator Confirmed'", $view);
        $this->assertStringContainsString('const wasActive = active;', $view);
        $this->assertStringContainsString('if (wasActive && !active)', $view);
        $this->assertStringContainsString('window.location.replace(window.location.href)', $view);

        // No periodic full-page refresh loop is reintroduced.
        $this->assertStringNotContainsString('window.location.reload()', $view);
    }
}
