<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChoiceOptimizationRunComponentSelectionContractTest extends TestCase
{
    #[Test]
    public function run_ui_defaults_available_historical_sources_and_shows_mandatory_track_filter(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $this->assertStringContainsString('name="include_previous_bcs"', $view);
        $this->assertStringContainsString('@checked($previousBcsRunAvailable)', $view);
        $this->assertStringContainsString('name="include_google_form"', $view);
        $this->assertStringContainsString('Latest valid Google Form dataset', $view);
        $this->assertStringContainsString('Written-track Filter — mandatory / runs last', $view);
    }

    #[Test]
    public function selected_historical_sources_are_consolidated_once_before_candidate_cutoff(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php'));
        $consolidated = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationConsolidatedHistoricalRecommendationService.php'));
        $this->assertStringContainsString('rebuild($includePreviousBcs, $includeGoogleForm, $previousBcsSourceIds, $googleFormBatchId)', $service);
        $this->assertStringContainsString("'source' => 'previous_bcs_repository'", $consolidated);
        $this->assertStringContainsString("'source' => 'google_form'", $consolidated);
        $this->assertStringContainsString('conflicts\' => 0', $consolidated);
    }

    #[Test]
    public function queued_run_freezes_exact_source_ids_and_google_form_batch(): void
    {
        $job = file_get_contents(app_path('Jobs/ProcessChoiceOptimizationHistoricalChoice.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $this->assertStringContainsString('public readonly array $previousBcsSourceIds = []', $job);
        $this->assertStringContainsString('public readonly ?int $googleFormBatchId = null', $job);
        $this->assertStringContainsString("'previous_bcs_source_ids' => \$previousBcsSourceIds", $controller);
        $this->assertStringContainsString("'google_form_batch_id' => \$googleFormBatchId", $controller);
    }
}
