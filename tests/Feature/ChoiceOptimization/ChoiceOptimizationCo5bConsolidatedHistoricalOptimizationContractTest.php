<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo5bConsolidatedHistoricalOptimizationContractTest extends TestCase
{
    public function test_consolidated_snapshot_schema_and_reset_registry_are_registered(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_08_30_224000_create_choice_optimization_consolidated_historical_recommendations.php'));
        $reset = file_get_contents(config_path('development-module-reset.php'));

        $this->assertStringContainsString('choice_optimization_consolidated_historical_recommendations', $migration);
        $this->assertStringContainsString('consolidation_status', $migration);
        $this->assertStringContainsString('sources', $migration);
        $this->assertStringContainsString('conflict_cadres', $migration); // legacy-compatible storage column
        $this->assertStringContainsString("co_chr_reg_bcs_uq", $migration);
        $this->assertStringContainsString('choice_optimization_consolidated_historical_recommendations', $reset);
    }

    public function test_previous_bcs_and_google_form_are_consolidated_before_single_optimization_pass(): void
    {
        $consolidated = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationConsolidatedHistoricalRecommendationService.php'));
        $optimizer = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php'));

        $this->assertStringContainsString("'source' => 'previous_bcs_repository'", $consolidated);
        $this->assertStringContainsString("'source' => 'google_form'", $consolidated);
        $this->assertStringContainsString('ChoiceOptimizationGoogleFormRecommendation::query()', $consolidated);
        $this->assertStringContainsString("where('match_status', 'matched')", $consolidated);
        $this->assertStringContainsString('$consolidationSummary = $this->consolidated->rebuild()', $optimizer);
        $this->assertStringContainsString('ChoiceOptimizationConsolidatedHistoricalRecommendation::query()', $optimizer);
    }

    public function test_google_form_no_bypasses_and_undecided_or_running_processing_blocks_snapshot(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationConsolidatedHistoricalRecommendationService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));

        $this->assertStringContainsString('google_form_enabled === null', $service);
        $this->assertStringContainsString('if ($setting->google_form_enabled)', $service);
        $this->assertStringContainsString('A Google Form batch is still processing', $service);
        $this->assertStringContainsString('Decide Google Form YES or NO before Consolidated Historical Choice Optimization.', $controller);
    }

    public function test_source_disagreement_is_non_blocking_and_any_unique_cadre_can_define_earliest_cutoff(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationConsolidatedHistoricalRecommendationService.php'));
        $optimizer = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php'));

        $this->assertStringContainsString("\$grouped[\$key]['cadres'][\$cadre] = true", $service);
        $this->assertStringContainsString("'consolidation_status' => 'resolved'", $service);
        $this->assertStringContainsString("'multi_cadre_keys' => \$multiCadreKeys", $service);
        $this->assertStringNotContainsString('HISTORICAL_RECOMMENDATION_SOURCE_CONFLICT', $optimizer);
        $this->assertStringContainsString('Source disagreement is intentionally non-blocking', $optimizer);
        $this->assertStringContainsString("->unique(fn (string \$cadre): string => mb_strtoupper(\$cadre))", $optimizer);
        $this->assertStringContainsString("\$a['choice_index'] <=> \$b['choice_index']", $optimizer);
        $this->assertStringContainsString('$cutoff = $cutoffCandidates[0]', $optimizer);
    }

    public function test_no_matching_historical_cadre_leaves_choice_unchanged(): void
    {
        $optimizer = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php'));

        $this->assertStringContainsString('if ($cutoffCandidates === [])', $optimizer);
        $this->assertStringContainsString("'status' => 'UNCHANGED'", $optimizer);
        $this->assertStringContainsString("'final' => \$inputCodes", $optimizer);
    }

    public function test_finalization_rechecks_google_form_and_consolidated_hashes(): void
    {
        $finalization = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceFinalizationService.php'));
        $optimizer = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalChoiceService.php'));

        $this->assertStringContainsString("'google_form_snapshot_hash'", $optimizer);
        $this->assertStringContainsString("'consolidated_historical_hash'", $optimizer);
        $this->assertStringContainsString("'google_form_snapshot_hash'", $finalization);
        $this->assertStringContainsString("'consolidated_historical_hash'", $finalization);
    }

    public function test_ui_exposes_lineage_without_source_conflict_badge(): void
    {
        $landing = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $list = file_get_contents(resource_path('views/choice-optimization/historical-choices-index.blade.php'));
        $detail = file_get_contents(resource_path('views/choice-optimization/historical-choice-show.blade.php'));

        $this->assertStringContainsString('Consolidated Historical Choice Optimization', $landing);
        $this->assertStringContainsString('any matching cadre may define the cutoff', $landing);
        $this->assertStringContainsString('GOOGLE FORM', $list);
        $this->assertStringContainsString('PREVIOUS BCS REPOSITORY', $list);
        $this->assertStringNotContainsString('SOURCE CONFLICT', $list);
        $this->assertStringContainsString('Consolidated Historical Recommendations', $detail);
        $this->assertStringContainsString('Source / Provenance', $detail);
        $this->assertStringNotContainsString("'conflict'", $detail);
    }
}
