<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo310MinimalReviewAndTraceContractTest extends TestCase
{
    public function test_omr_batch_review_is_decision_focused_and_individual_page_preserves_full_trace(): void
    {
        $routes = file_get_contents(base_path('routes/choice-optimization.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $batchView = file_get_contents(resource_path('views/choice-optimization/omr-show.blade.php'));
        $detailView = file_get_contents(resource_path('views/choice-optimization/omr-row-detail.blade.php'));

        $this->assertStringContainsString("->name('omr.row.show')", $routes);
        $this->assertStringContainsString('public function showOmrRow(', $controller);
        $this->assertStringContainsString('ChoiceOptimizationEffectiveChoice', $controller);

        $this->assertStringContainsString('Processing Action', $batchView);
        $this->assertStringContainsString('Registration Choice', $batchView);
        $this->assertStringContainsString('Finalized Validated Choice', $batchView);
        $this->assertStringContainsString('OMR Options', $batchView);
        $this->assertStringContainsString('Details', $batchView);
        $this->assertStringNotContainsString('Expansion / Removal Details', $batchView);
        $this->assertStringNotContainsString('JSON_PRETTY_PRINT', $batchView);

        $this->assertStringContainsString('Choice Lineage', $detailView);
        $this->assertStringContainsString('Current Effective Choice', $detailView);
        $this->assertStringContainsString('Expansion / Removal Details', $detailView);
        $this->assertStringContainsString('Eligibility Evidence', $detailView);
        $this->assertStringContainsString('Operator / Resolution Audit', $detailView);
        $this->assertStringContainsString('Approved Effective Choice', $detailView);
    }
}
