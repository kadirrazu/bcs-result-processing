<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritIndividualViewVisibilityContractTest extends TestCase
{
    public function test_current_completed_merit_run_exposes_candidate_review_before_finalization(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $results = file_get_contents(resource_path('views/merit/results.blade.php'));
        $cadre = file_get_contents(resource_path('views/merit/cadre.blade.php'));
        $show = file_get_contents(resource_path('views/merit/show.blade.php'));

        $this->assertStringContainsString("['review_ready', 'finalized']", $controller);
        $this->assertStringContainsString('$result->run?->status === \'completed\'', $controller);
        $this->assertStringContainsString("Individual Merit View is available only for the current completed Merit run.", $controller);
        $this->assertStringContainsString("Review View", $results);
        $this->assertStringContainsString("Review View", $cadre);
        $this->assertStringContainsString("Individual Merit Review", $show);
        $this->assertStringContainsString("REVIEW_READY", $show);
        $this->assertStringContainsString("FINALIZED", $show);
    }
}
