<?php

namespace Tests\Feature\ChoiceOptimization;

use Tests\TestCase;

class ChoiceOptimizationCo4b3RowDetailMasterMenuGroupContractTest extends TestCase
{
    public function test_each_dataset_row_has_view_action_and_read_only_single_row_detail(): void
    {
        $routes = file_get_contents(base_path('routes/previous-bcs-repository.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PreviousBcsRepositoryController.php'));
        $show = file_get_contents(resource_path('views/previous-bcs-repository/show.blade.php'));
        $detail = file_get_contents(resource_path('views/previous-bcs-repository/row-detail.blade.php'));

        $this->assertStringContainsString("->name('datasets.rows.show')", $routes);
        $this->assertStringContainsString('public function rowDetail(', $controller);
        $this->assertStringContainsString('previous-bcs-repository.datasets.rows.show', $show);
        $this->assertStringContainsString('>View</a>', preg_replace('/\s+/', ' ', $show));

        $this->assertStringContainsString('Historical Recommendation Record', $detail);
        $this->assertStringContainsString('Read-only source record.', $detail);
        $this->assertStringContainsString('System Validation Details', $detail);
        $this->assertStringContainsString('Raw Source Payload', $detail);
    }

    public function test_historical_data_is_inside_master_data_below_registration_masters(): void
    {
        $nav = file_get_contents(resource_path('views/layouts/partials/main-navigation.blade.php'));

        $this->assertStringContainsString('<h6 class="dropdown-header">Registration Masters</h6>', $nav);
        $this->assertStringContainsString('<h6 class="dropdown-header">Historical Data</h6>', $nav);
        $this->assertStringContainsString('Previous BCS Repository', $nav);
        $this->assertStringNotContainsString('navbar-historical-data', $nav);

        $registration = strpos($nav, '<h6 class="dropdown-header">Registration Masters</h6>');
        $historical = strpos($nav, '<h6 class="dropdown-header">Historical Data</h6>');
        $repository = strpos($nav, 'Previous BCS Repository');

        $this->assertNotFalse($registration);
        $this->assertNotFalse($historical);
        $this->assertNotFalse($repository);
        $this->assertGreaterThan($registration, $historical);
        $this->assertGreaterThan($historical, $repository);
    }
}
