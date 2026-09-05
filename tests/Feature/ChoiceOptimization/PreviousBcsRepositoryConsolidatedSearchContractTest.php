<?php

namespace Tests\Feature\ChoiceOptimization;

use PHPUnit\Framework\TestCase;

class PreviousBcsRepositoryConsolidatedSearchContractTest extends TestCase
{
    public function test_repository_exposes_dedicated_consolidated_candidate_search(): void
    {
        $routes = file_get_contents(base_path('routes/previous-bcs-repository.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PreviousBcsRepositoryController.php'));
        $index = file_get_contents(resource_path('views/previous-bcs-repository/index.blade.php'));
        $view = file_get_contents(resource_path('views/previous-bcs-repository/search.blade.php'));

        $this->assertStringContainsString("/search", $routes);
        $this->assertStringContainsString("function search(Request \$request)", $controller);
        $this->assertStringContainsString('current_effective_dataset_id', $controller);
        $this->assertStringContainsString("->where('previous_bcs_repository_rows.name', 'like'", $controller);
        $this->assertStringContainsString('Consolidated Candidate Search', $index);
        $this->assertStringContainsString('Historical superseded dataset versions are excluded.', $view);
    }
}
