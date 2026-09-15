<?php

namespace Tests\Feature\Dependencies;

use Tests\TestCase;

final class DownstreamStaleDependencyGraphContractTest extends TestCase
{
    public function test_latest_dependency_graph_preserves_merit_choice_independence_and_allocation_stale_coupling(): void
    {
        $service = file_get_contents(app_path('Services/Dependencies/DownstreamStalePropagationService.php'));
        $circular = file_get_contents(app_path('Services/Circular/CircularDatasetService.php'));
        $tabulation = file_get_contents(app_path('Services/Tabulation/TabulationStaleService.php'));
        $choiceReadiness = file_get_contents(app_path('Services/ChoiceValidation/ChoiceValidationReadinessService.php'));

        self::assertStringContainsString("'viva' => ['tabulation', 'choice_validation', 'merit', 'allocation']", $service);
        self::assertStringContainsString("'circular' => ['choice_validation', 'choice_optimization', 'merit', 'allocation']", $service);
        self::assertStringNotContainsString("'circular' => ['tabulation'", $service);
        self::assertStringContainsString("'choice_validation' => ['choice_optimization', 'allocation']", $service);
        self::assertStringContainsString("'tabulation' => ['merit', 'allocation']", $service);
        self::assertStringContainsString("'merit' => ['allocation']", $service);
        self::assertStringContainsString("'choice_optimization' => ['allocation']", $service);
        self::assertStringContainsString('staleFromDirectInputChange', $service);

        self::assertStringNotContainsString("'tabulation' => 'Tabulation'", $circular);
        self::assertStringContainsString("\$this->downstream->propagate('tabulation',\$reason)", str_replace(' ', '', $tabulation));
        self::assertStringContainsString('$this->vivaStale->synchronize();', $choiceReadiness);
        self::assertStringContainsString('$this->circularStale->synchronize();', $choiceReadiness);
    }
}
