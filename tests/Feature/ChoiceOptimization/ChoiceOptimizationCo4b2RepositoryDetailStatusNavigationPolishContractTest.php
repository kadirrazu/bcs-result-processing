<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4b2RepositoryDetailStatusNavigationPolishContractTest extends TestCase
{
    public function test_dataset_has_full_read_only_detail_page_with_all_fields_and_system_status(): void
    {
        $routes = file_get_contents(base_path('routes/previous-bcs-repository.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PreviousBcsRepositoryController.php'));
        $show = file_get_contents(resource_path('views/previous-bcs-repository/show.blade.php'));
        $detail = file_get_contents(resource_path('views/previous-bcs-repository/detail.blade.php'));

        $this->assertStringContainsString("->name('datasets.detail')", $routes);
        $this->assertStringContainsString('public function detail(', $controller);
        $this->assertStringContainsString('View Full Dataset', $show);

        foreach ([
            'Reg', 'Name', 'Father', 'Mother', 'b_date', 'dob', 'District',
            'SSC Roll', 'SSC Year', 'HSC Roll', 'HSC Year', 'NID', 'Cadre',
            'System Status',
        ] as $label) {
            $this->assertStringContainsString($label, $detail);
        }

        $this->assertStringContainsString('Read-only historical data.', $detail);
        $this->assertStringContainsString('View system details', $detail);
        $this->assertStringContainsString('validation_warnings', $detail);
        $this->assertStringContainsString('validation_errors', $detail);
    }

    public function test_repository_status_badges_reflect_semantic_state(): void
    {
        $index = file_get_contents(resource_path('views/previous-bcs-repository/index.blade.php'));

        $this->assertStringContainsString("'effective' => 'bg-green-lt'", $index);
        $this->assertStringContainsString("'validated' => 'bg-blue-lt'", $index);
        $this->assertStringContainsString("'superseded' => 'bg-secondary-lt'", $index);
        $this->assertStringContainsString("'validation_failed' => 'bg-red-lt'", $index);
    }

    public function test_previous_bcs_repository_is_under_historical_data_inside_master_data(): void
    {
        $nav = file_get_contents(resource_path('views/layouts/partials/main-navigation.blade.php'));

        $this->assertStringContainsString('<h6 class="dropdown-header">Registration Masters</h6>', $nav);
        $this->assertStringContainsString('<h6 class="dropdown-header">Historical Data</h6>', $nav);
        $this->assertStringContainsString('Previous BCS Repository', $nav);
        $this->assertStringNotContainsString('navbar-historical-data', $nav);
    }
}
