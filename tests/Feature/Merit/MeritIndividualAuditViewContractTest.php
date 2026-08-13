<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritIndividualAuditViewContractTest extends TestCase
{
    public function test_finalized_candidate_view_exposes_upstream_tabulation_choice_and_ranking_context(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $view = file_get_contents(resource_path('views/merit/show.blade.php'));

        foreach ([
            'TabulationResult::query()->findOrFail',
            'Registration::query()->findOrFail',
            'PreliminaryResult::query()',
            'WrittenResult::query()->findOrFail',
            'VivaResult::query()->findOrFail',
            'ChoiceValidationResult::query()',
        ] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }

        foreach ([
            'Upstream Finalized Data',
            'Registration',
            'Preliminary',
            'Written',
            'Viva',
            'Finalized Tabulation Ranking Inputs',
            'Finalized Choice Validation',
            'Merit Source Authority',
            'Generated Merit Ranking',
            'Cadre-wise Merit Positions',
            'all_merit_tech',
        ] as $needle) {
            $this->assertStringContainsString($needle, $view);
        }
    }
}
