<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

final class ChoiceOptimizationLatestGoogleBatchHistoricalSourceUsageContractTest extends TestCase
{
    public function test_latest_google_form_batch_is_the_only_authoritative_google_form_input(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $consolidated = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationConsolidatedHistoricalRecommendationService.php'));
        $merge = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationGoogleFormMergeService.php'));
        $googleController = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationGoogleFormController.php'));
        $showView = file_get_contents(resource_path('views/choice-optimization/google-form-show.blade.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));

        $this->assertStringContainsString("where('source_batch_id', (int) $latestGoogleFormBatch->id)", $controller);
        $this->assertStringContainsString('latestGoogleFormAuthorityBatch', $consolidated);
        $this->assertStringContainsString("where('source_batch_id', '<>', (int) $batch->id)", $merge);
        $this->assertStringContainsString('Only the latest Google Form batch can be approved/merged', $googleController);
        $this->assertStringContainsString('HISTORY ONLY', $showView);
        $this->assertStringContainsString('Latest Batch Accepted', $view);
        $this->assertStringContainsString('older batches are history only', $view);
    }

    public function test_previous_bcs_sources_can_be_included_or_excluded_without_deleting_evidence(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_09_03_210000_add_historical_source_usage_to_choice_optimization.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php'));
        $consolidated = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationConsolidatedHistoricalRecommendationService.php'));
        $routes = file_get_contents(base_path('routes/choice-optimization.php'));
        $view = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));

        $this->assertStringContainsString("boolean('included_in_optimization')", $migration);
        $this->assertStringContainsString('updateHistoricalSourceUsage', $controller);
        $this->assertStringContainsString("where('included_in_optimization', true)", $service);
        $this->assertStringContainsString("where('included_in_optimization', true)", $consolidated);
        $this->assertStringContainsString('historical.source-usage', $routes);
        $this->assertStringContainsString('Include Selected', $view);
        $this->assertStringContainsString('Exclude Selected', $view);
        $this->assertStringContainsString('Pulled data and match evidence were preserved', $controller);
    }
}
