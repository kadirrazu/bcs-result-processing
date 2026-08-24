<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c35QueueFinalizationHashHotfixContractTest extends TestCase
{
    public function test_output_hash_uses_one_canonical_shape_for_processing_and_database_reverification(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php')
        );

        $this->assertStringContainsString('canonicalOutputRow', $service);
        $this->assertStringContainsString('decodeJsonValue', $service);
        $this->assertStringContainsString('outputHashFromDatabase', $service);
        $this->assertStringContainsString('hashOutputRows', $service);

        $this->assertStringContainsString(
            '\'historical_recommendations\' => array_values((array) ($row[\'historical_recommendations\'] ?? []))',
            $service
        );
        $this->assertStringContainsString(
            '\'blocking_issues\' => array_values((array) ($row[\'blocking_issues\'] ?? []))',
            $service
        );
    }

    public function test_allocation_ready_finalization_is_queue_based_and_polled(): void
    {
        $job = file_get_contents(
            app_path('Jobs/FinalizeChoiceOptimizationHistoricalChoice.php')
        );
        $controller = file_get_contents(
            app_path('Http/Controllers/ChoiceOptimizationController.php')
        );
        $view = file_get_contents(
            resource_path('views/choice-optimization/historical-choices-index.blade.php')
        );

        $this->assertStringContainsString('implements ShouldQueue', $job);
        $this->assertStringContainsString('ExaminationConnectionManager', $job);
        $this->assertStringContainsString('ChoiceOptimizationHistoricalChoiceFinalizationService', $job);

        $this->assertStringContainsString("'status' => 'finalization_queued'", $controller);
        $this->assertStringContainsString('CHOICE_OPTIMIZATION_FINALIZATION_QUEUED', $controller);
        $this->assertStringContainsString('FinalizeChoiceOptimizationHistoricalChoice::dispatch', $controller);
        $this->assertStringContainsString("'finalization_queued', 'finalizing'", $controller);

        $this->assertStringContainsString('Finalizing Allocation-ready Choice', $view);
        $this->assertStringContainsString('fetch(url', $view);
        $this->assertStringContainsString('window.location.replace(window.location.href)', $view);
    }
}
