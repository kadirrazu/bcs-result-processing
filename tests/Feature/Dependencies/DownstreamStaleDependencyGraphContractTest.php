<?php

namespace Tests\Feature\Dependencies;

use Tests\TestCase;

final class DownstreamStaleDependencyGraphContractTest extends TestCase
{
    public function test_dependency_graph_is_correct_and_circular_does_not_stale_tabulation(): void
    {
        $service = file_get_contents(
            app_path('Services/Dependencies/DownstreamStalePropagationService.php')
        );
        $circular = file_get_contents(
            app_path('Services/Circular/CircularDatasetService.php')
        );
        $tabulation = file_get_contents(
            app_path('Services/Tabulation/TabulationStaleService.php')
        );
        $choiceReadiness = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationReadinessService.php')
        );

        $this->assertStringContainsString(
            "'viva' => ['tabulation', 'choice_validation', 'merit']",
            $service
        );
        $this->assertStringContainsString(
            "'circular' => ['choice_validation', 'merit']",
            $service
        );
        $this->assertStringNotContainsString(
            "'circular' => ['tabulation'",
            $service
        );
        $this->assertStringContainsString(
            "'choice_validation' => ['merit']",
            $service
        );
        $this->assertStringContainsString(
            "'tabulation' => ['merit']",
            $service
        );

        $this->assertStringNotContainsString(
            "'tabulation' => 'Tabulation'",
            $circular
        );

        $this->assertStringContainsString(
            "\$this->downstream->propagate('tabulation',\$reason)",
            str_replace(' ', '', $tabulation)
        );

        $this->assertStringContainsString(
            '$this->vivaStale->synchronize();',
            $choiceReadiness
        );
        $this->assertStringContainsString(
            '$this->circularStale->synchronize();',
            $choiceReadiness
        );
    }
}
