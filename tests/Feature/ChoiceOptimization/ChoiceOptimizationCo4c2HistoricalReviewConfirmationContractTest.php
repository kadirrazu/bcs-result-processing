<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4c2HistoricalReviewConfirmationContractTest extends TestCase
{
    public function test_historical_review_resolution_is_audited_and_competing_rows_are_closed(): void
    {
        $migration = file_get_contents(
            database_path('examination-migrations/2026_08_24_203500_add_review_resolution_to_choice_optimization_historical_matches.php')
        );
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalReviewService.php')
        );
        $pull = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalPullService.php')
        );

        $this->assertStringContainsString('resolution_status', $migration);
        $this->assertStringContainsString('resolution_reason', $migration);
        $this->assertStringContainsString('resolved_by', $migration);
        $this->assertStringContainsString('resolved_at', $migration);
        $this->assertStringContainsString("'auto_confirmed'", $migration);
        $this->assertStringContainsString("'pending'", $migration);

        $this->assertStringContainsString("['confirm', 'reject']", $service);
        $this->assertStringContainsString("'operator_confirmed'", $service);
        $this->assertStringContainsString("'operator_rejected'", $service);
        $this->assertStringContainsString("'competing_rejected'", $service);
        $this->assertStringContainsString('HISTORICAL_MATCH_CONFIRMED', $service);
        $this->assertStringContainsString('HISTORICAL_MATCH_REJECTED', $service);
        $this->assertStringContainsString('refreshSourceMetrics', $service);

        $this->assertStringContainsString("'resolution_status' => \$status === 'matched' ? 'auto_confirmed' : 'pending'", $pull);
    }

    public function test_operator_review_page_compares_current_and_previous_identity_and_requires_reason(): void
    {
        $routes = file_get_contents(base_path('routes/choice-optimization.php'));
        $controller = file_get_contents(app_path('Http/Controllers/ChoiceOptimizationController.php'));
        $list = file_get_contents(resource_path('views/choice-optimization/historical-show.blade.php'));
        $detail = file_get_contents(resource_path('views/choice-optimization/historical-match-show.blade.php'));

        $this->assertStringContainsString("->name('historical.matches.show')", $routes);
        $this->assertStringContainsString("->name('historical.matches.resolve')", $routes);
        $this->assertStringContainsString('public function showHistoricalMatch(', $controller);
        $this->assertStringContainsString('public function resolveHistoricalMatch(', $controller);
        $this->assertStringContainsString("'reason' => ['required', 'string', 'max:2000']", $controller);

        $this->assertStringContainsString('Review Next', $list);
        $this->assertStringContainsString('Operator Confirmed', $list);
        $this->assertStringContainsString('Historical Match Review', $detail);
        $this->assertStringContainsString('Current Candidate <strong>(BCS {{ $currentBcsNumber ?: \'—\' }})</strong>', $detail);
        $this->assertStringContainsString('Previous BCS Record', $detail);
        $this->assertStringContainsString('Confirm Match', $detail);
        $this->assertStringContainsString('Reject Match', $detail);
        $this->assertStringContainsString('Administrative reason', $detail);
        $this->assertStringContainsString("fetch(form.action", $detail);
        $this->assertStringContainsString('next_review_url', $controller);
    }

    public function test_confirmed_historical_recommendation_has_a_single_downstream_read_boundary(): void
    {
        $service = file_get_contents(
            app_path('Services/ChoiceOptimization/ChoiceOptimizationHistoricalRecommendationService.php')
        );

        $this->assertStringContainsString('confirmedForRegistration', $service);
        $this->assertStringContainsString('confirmedForSource', $service);
        $this->assertStringContainsString("where('match_status', 'matched')", $service);
        $this->assertStringContainsString("orderBy('previous_bcs_number')", $service);
    }
}
