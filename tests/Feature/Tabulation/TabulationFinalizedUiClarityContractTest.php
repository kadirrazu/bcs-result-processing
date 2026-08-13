<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

class TabulationFinalizedUiClarityContractTest extends TestCase
{
    public function test_finalized_view_uses_authoritative_written_track_and_canonical_tokens(): void
    {
        $show = file_get_contents(resource_path('views/tabulation/show.blade.php'));
        $model = file_get_contents(app_path('Models/TabulationResult.php'));

        $this->assertStringContainsString('written_qualified_track?->value', $show);
        $this->assertStringContainsString('Written Qualified Track Snapshot', $show);
        $this->assertStringContainsString("return 'TRACK_FAILED'", $model);
        $this->assertStringContainsString("return 'NOT_APPLICABLE'", $model);
        $this->assertStringNotContainsString("return 'TRACK FAILED'", $model);
        $this->assertStringNotContainsString("return 'NOT APPLICABLE'", $model);
    }

    public function test_results_separate_population_from_merit_eligibility_and_use_track_badges(): void
    {
        $view = file_get_contents(resource_path('views/tabulation/results.blade.php'));
        $summary = file_get_contents(app_path('Services/Tabulation/TabulationReviewSummaryService.php'));

        $this->assertStringContainsString('Tabulated Population', $view);
        $this->assertStringContainsString('Merit Eligibility Outcome', $view);
        $this->assertStringContainsString('Population by Written Qualified Track', $view);
        $this->assertStringContainsString('general_only_track_population', $view);
        $this->assertStringContainsString('general_only_not_merit_eligible', $view);
        $this->assertStringContainsString('NOT_MERIT_ELIGIBLE', $view);
        $this->assertStringContainsString("'GG' => 'bg-blue-lt text-blue'", $view);
        $this->assertStringContainsString("'GT' => 'bg-teal-lt text-teal'", $view);

        $this->assertStringContainsString("'general_only_track_population' =>", $summary);
        $this->assertStringContainsString("'technical_only_track_population' =>", $summary);
        $this->assertStringContainsString("'both_track_population' =>", $summary);
    }
}
