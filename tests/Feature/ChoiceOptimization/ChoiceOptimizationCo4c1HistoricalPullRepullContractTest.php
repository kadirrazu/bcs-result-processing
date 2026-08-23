<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c1HistoricalPullRepullContractTest extends TestCase
{
    public function test_historical_pull_is_examination_scoped_queue_based_and_repull_replaces_snapshot(): void
    {
        $migration = file_get_contents(
            database_path('examination-migrations/2026_08_24_001000_create_choice_optimization_historical_pull.php')
        );
        $job = file_get_contents(app_path('Jobs/ProcessChoiceOptimizationHistoricalPull.php'));
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalPullService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $routes = file_get_contents(base_path('routes/choice-optimization.php'));

        $this->assertStringContainsString("Schema::connection('exam')", $migration);
        $this->assertStringContainsString("choice_optimization_historical_sources", $migration);
        $this->assertStringContainsString("choice_optimization_historical_matches", $migration);
        $this->assertStringContainsString("unique('co_hist_src_bcs_uq')", $migration);

        $this->assertStringContainsString('implements ShouldQueue', $job);
        $this->assertStringContainsString('ExaminationConnectionManager', $job);
        $this->assertStringContainsString("config('choice-optimization.queue', 'imports')", $job);

        $this->assertStringContainsString("where('historical_source_id'", $service);
        $this->assertStringContainsString("->delete();", $service);
        $this->assertStringContainsString('HISTORICAL_PULL_COMPLETED', $service);

        $this->assertStringContainsString('updateOrCreate(', $controller);
        $this->assertStringContainsString("'bcs_numbers' => ['required', 'array', 'min:1']", $controller);
        $this->assertStringContainsString('HISTORICAL_REPULL_QUEUED', $controller);
        $this->assertStringContainsString('ProcessChoiceOptimizationHistoricalPull::dispatch', $controller);
        $this->assertStringContainsString("->name('historical.pull')", $routes);
    }

    public function test_matching_uses_written_qualified_candidates_and_exact_primary_identity_with_supporting_review(): void
    {
        $service = file_get_contents(app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalPullService.php'));

        $this->assertStringContainsString("where('status', 'active')", $service);
        $this->assertStringContainsString("whereNotNull('written_qualified_track')", $service);
        $this->assertStringContainsString('WrittenProcessingState', $service);

        $this->assertStringContainsString('ssc_roll', $service);
        $this->assertStringContainsString('ssc_year', $service);
        $this->assertStringContainsString("birth_date->format('Y-m-d')", $service);
        $this->assertStringContainsString("b_date->format('Y-m-d')", $service);

        $this->assertStringContainsString("'name' => \$this->textEvidence", $service);
        $this->assertStringContainsString("'nid' => \$this->identityEvidence", $service);
        $this->assertStringContainsString("'hsc_roll' => \$this->identityEvidence", $service);
        $this->assertStringContainsString("'hsc_year' => \$this->numericEvidence", $service);
        $this->assertStringContainsString("'secondary_dob' => \$this->dateEvidence", $service);
        $this->assertStringContainsString("CORE_EXACT_SUPPORTING_REVIEW", $service);
        $this->assertStringContainsString("CORE_EXACT", $service);
    }

    public function test_choice_optimization_landing_exposes_pull_repull_and_effective_version_update_state(): void
    {
        $view = file_get_contents(resource_path('views/choice-optimization/index.blade.php'));
        $show = file_get_contents(resource_path('views/choice-optimization/historical-show.blade.php'));
        $reset = file_get_contents(config_path('development-module-reset.php'));

        $this->assertStringContainsString('Historical Previous BCS Sources', $view);
        $this->assertStringContainsString('UPDATE AVAILABLE', $view);
        $this->assertStringContainsString('Pull / Re-pull Selected', $view);
        $this->assertStringContainsString('bcs_numbers[]', $view);
        $this->assertStringContainsString('Matched Historical Recommendations', $show);
        $this->assertStringContainsString('MATCHED rows have exact primary identity', $show);
        $this->assertStringContainsString('window.location.replace(window.location.href)', $show);
        $this->assertStringContainsString('choice_optimization_historical_matches', $reset);
        $this->assertStringContainsString('choice_optimization_historical_sources', $reset);
    }
}
