<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

final class TabulationPagePerformanceContractTest extends TestCase
{
    public function test_landing_and_results_do_not_full_rehash_on_each_page_load_and_summary_is_aggregated(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/TabulationController.php'));
        $integrity = file_get_contents(app_path('Services/Tabulation/TabulationDatasetIntegrityService.php'));
        $stale = file_get_contents(app_path('Services/Tabulation/TabulationStaleService.php'));
        $summary = file_get_contents(app_path('Services/Tabulation/TabulationReviewSummaryService.php'));
        $finalizedDataset = file_get_contents(app_path('Services/Tabulation/TabulationFinalizedDatasetService.php'));

        $this->assertStringContainsString('$readinessInspection = $readiness->inspect();', $controller);
        $this->assertStringContainsString('$stale->synchronize($readinessInspection, false);', $controller);
        $this->assertStringContainsString('$integrity->inspect(null, false)', $controller);
        $this->assertStringContainsString('$integrity->inspect((int) $run->id, false)', $controller);

        $this->assertStringContainsString('bool $recompute = true', $integrity);
        $this->assertStringContainsString('HASH_VERIFIED_AT_FINALIZATION', $integrity);
        $this->assertStringContainsString('bool $verifyDatasetHash = true', $stale);

        $this->assertStringNotContainsString('countStatus(', $summary);
        $this->assertStringNotContainsString('countWarning(', $summary);
        $this->assertStringContainsString('SUM(CASE WHEN t.validation_status', $summary);
        $this->assertStringContainsString("groupBy('written_qualified_track')", $summary);

        // Downstream safety is preserved: Merit readiness still performs a fresh dataset re-hash.
        $this->assertStringContainsString('$this->hasher->hash', $finalizedDataset);
        $this->assertStringContainsString('TABULATION_DATASET_HASH_MISMATCH', $finalizedDataset);
    }
}
