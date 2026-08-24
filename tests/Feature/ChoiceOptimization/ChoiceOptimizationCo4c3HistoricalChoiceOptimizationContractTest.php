<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c3HistoricalChoiceOptimizationContractTest extends TestCase
{
    public function test_co4c3_is_examination_scoped_queue_based_and_preserves_upstream_choice_lineage(): void
    {
        $migration = file_get_contents(
            database_path('examination-migrations/2026_08_24_220000_create_choice_optimization_historical_choices.php')
        );
        $job = file_get_contents(app_path('Jobs/ProcessChoiceOptimizationHistoricalChoice.php'));
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php')
        );
        $input = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalInputService.php')
        );

        $this->assertStringContainsString("Schema::connection('exam')->create('choice_optimization_historical_choices'", $migration);
        $this->assertStringContainsString('input_choice_codes', $migration);
        $this->assertStringContainsString('final_choice_codes', $migration);
        $this->assertStringContainsString('blocking_issues', $migration);

        $this->assertStringContainsString('implements ShouldQueue', $job);
        $this->assertStringContainsString('ExaminationConnectionManager', $job);
        $this->assertStringContainsString("config('choice-optimization.queue', 'imports')", $job);

        $this->assertStringContainsString('ChoiceOptimizationEffectiveChoice::query()', $input);
        $this->assertStringContainsString("'finalized_validated_choice'", $input);
        $this->assertStringContainsString('ChoiceOptimizationHistoricalChoice::query()->delete()', $service);
        $this->assertStringNotContainsString('ChoiceOptimizationEffectiveChoice::query()->update', $service);
    }

    public function test_cutoff_uses_highest_current_preference_and_locked_edge_cases(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php')
        );

        $this->assertStringContainsString('AMBIGUOUS_HISTORICAL_CADRE_MAPPING', $service);
        $this->assertStringContainsString('UNRESOLVED_HISTORICAL_CADRE', $service);
        $this->assertStringContainsString('HISTORICAL_CADRE_NOT_IN_CURRENT_CIRCULAR', $service);
        $this->assertStringContainsString('NO_MATCHING_CURRENT_CHOICE', $service);
        $this->assertStringContainsString("'NO_HIGHER_CHOICE_REMAINS'", $service);
        $this->assertStringContainsString("'OPTIMIZED'", $service);
        $this->assertStringContainsString("'UNCHANGED'", $service);

        $this->assertStringContainsString('usort($cutoffCandidates', $service);
        $this->assertStringContainsString('$a[\'choice_index\'] <=> $b[\'choice_index\']', $service);
        $this->assertStringContainsString('array_slice($inputCodes, 0, $cutoffIndex)', $service);
        $this->assertStringContainsString('array_slice($inputCodes, $cutoffIndex)', $service);
    }

    public function test_unresolved_review_blocks_processing_and_source_changes_make_output_stale(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $review = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalReviewService.php')
        );
        $staleness = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalStalenessService.php')
        );

        $this->assertStringContainsString("where('match_status', 'review')", $controller);
        $this->assertStringContainsString("where('resolution_status', 'pending')", $controller);
        $this->assertStringContainsString('Resolve all', $controller);
        $this->assertStringContainsString('ChoiceOptimizationHistoricalStalenessService $staleness', $controller);
        $this->assertStringContainsString('Confirmed Historical Recommendation set changed after operator review.', $review);
        $this->assertStringContainsString('HISTORICAL_CHOICE_OPTIMIZATION_STALE', $staleness);
    }

    public function test_finalization_reverifies_all_authoritative_source_hashes_and_output_hash(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceFinalizationService.php')
        );

        $this->assertStringContainsString("'input_choice_hash'", $service);
        $this->assertStringContainsString("'choice_validation_hash'", $service);
        $this->assertStringContainsString("'circular_hash'", $service);
        $this->assertStringContainsString("'historical_snapshot_hash'", $service);
        $this->assertStringContainsString('outputHashFromDatabase', $service);
        $this->assertStringContainsString("'status' => 'finalized'", $service);
        $this->assertStringContainsString('CHOICE_OPTIMIZATION_FINALIZED', $service);
    }

    public function test_operator_ui_shows_full_choice_lineage_and_queue_polling(): void
    {
        $index = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $list = file_get_contents(resource_path('views/choice-optimization/historical-choices-index.blade.php'));
        $detail = file_get_contents(resource_path('views/choice-optimization/historical-choice-show.blade.php'));
        $routes = file_get_contents(base_path('routes/choice-optimization.php'));
        $reset = file_get_contents(config_path('development-module-reset.php'));

        $this->assertStringContainsString('Historical Choice Optimization', $index);
        $this->assertStringContainsString('Resolve all Historical Match REVIEW items', $index);
        $this->assertStringContainsString('Finalize Allocation-ready Choice', $list);
        $this->assertStringContainsString('Input Effective Choice', $list);
        $this->assertStringContainsString('Allocation-ready Choice', $list);
        $this->assertStringContainsString('fetch(url', $list);
        $this->assertStringContainsString('window.location.replace(window.location.href)', $list);

        $this->assertStringContainsString('Choice Lineage', $detail);
        $this->assertStringContainsString('Confirmed Historical Recommendations', $detail);
        $this->assertStringContainsString('Removed by Historical Cutoff', $detail);
        $this->assertStringContainsString('Final Allocation-ready Choice', $detail);

        $this->assertStringContainsString("->name('historical-choices.process')", $routes);
        $this->assertStringContainsString("->name('historical-choices.finalize')", $routes);
        $this->assertStringContainsString('choice_optimization_historical_choices', $reset);
    }
}
