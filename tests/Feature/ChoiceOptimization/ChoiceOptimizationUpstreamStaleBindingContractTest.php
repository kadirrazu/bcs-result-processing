<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChoiceOptimizationUpstreamStaleBindingContractTest extends TestCase
{
    #[Test]
    public function dependency_graph_propagates_choice_validation_to_optimization(): void
    {
        $service = file_get_contents(app_path('Services/Dependencies/DownstreamStalePropagationService.php'));
        $this->assertStringContainsString("'choice_validation' => ['choice_optimization', 'merit']", $service);
        $this->assertStringContainsString('markChoiceOptimization', $service);
    }

    #[Test]
    public function optimization_and_allocation_have_defensive_choice_validation_gates(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $readiness = file_get_contents(app_path('Services/Allocation/AllocationReadinessService.php'));

        $this->assertStringContainsString('ChoiceOptimizationUpstreamStaleService', $controller);
        $this->assertStringContainsString('storedFinalizedSummary();', $controller);
        $this->assertStringContainsString('choice_validation_hash', $readiness);
        $this->assertStringContainsString('CHOICE_OPTIMIZATION_CHOICE_VALIDATION_SNAPSHOT_MISMATCH', $readiness);
    }
}
