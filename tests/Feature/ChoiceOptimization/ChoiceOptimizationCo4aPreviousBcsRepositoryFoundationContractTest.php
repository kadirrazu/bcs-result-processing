<?php

namespace Tests\Feature\ChoiceOptimization;

use App\Services\PreviousBcsRepository\PreviousBcsDateNormalizer;
use Tests\TestCase;

class ChoiceOptimizationCo4aPreviousBcsRepositoryFoundationContractTest extends TestCase
{
    public function test_global_previous_bcs_repository_is_central_versioned_and_queue_staged(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_23_230000_create_previous_bcs_recommendation_repository.php'));
        $service = file_get_contents(app_path('Services/PreviousBcsRepository/PreviousBcsRepositoryImportService.php'));
        $job = file_get_contents(app_path('Jobs/ProcessPreviousBcsRepositoryImport.php'));
        $routes = file_get_contents(base_path('routes/previous-bcs-repository.php'));
        $web = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("Schema::create('previous_bcs_repositories'", $migration);
        $this->assertStringContainsString("Schema::create('previous_bcs_repository_datasets'", $migration);
        $this->assertStringContainsString("Schema::create('previous_bcs_repository_rows'", $migration);
        $this->assertStringContainsString("unique(['repository_id', 'version']", $migration);

        $this->assertStringContainsString('ProcessPreviousBcsRepositoryImport::dispatch', $service);
        $this->assertStringContainsString("status' => 'queued'", $service);
        $this->assertStringContainsString("status' => 'staged'", $service);
        $this->assertStringContainsString('firstOrCreate([\'bcs_number\' => $bcsNumber])', $service);
        $this->assertStringContainsString("lockForUpdate()", $service);

        $this->assertStringContainsString('implements ShouldQueue', $job);
        $this->assertStringContainsString("onQueue('imports')", $job);
        $this->assertStringContainsString("name('previous-bcs-repository.')", $routes);
        $this->assertStringContainsString("previous-bcs-repository.php", $web);
    }

    public function test_previous_bcs_excel_contract_and_date_normalization_are_locked(): void
    {
        $service = file_get_contents(app_path('Services/PreviousBcsRepository/PreviousBcsRepositoryImportService.php'));
        $view = file_get_contents(resource_path('views/previous-bcs-repository/index.blade.php'));

        foreach ([
            'reg', 'name', 'fname', 'mname', 'b_date', 'dob', 'dist_name',
            'ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'nid_no', 'cadre',
        ] as $column) {
            $this->assertStringContainsString("'{$column}'", $service);
        }

        $this->assertStringContainsString("OPTIONAL_COLUMNS = ['fname', 'mname', 'dob', 'dist_name', 'nid_no']", $service);
        $this->assertStringContainsString('headers must exactly match', strtolower($service));
        $this->assertStringContainsString('BCS Number', $view);
        $this->assertStringContainsString('Re-upload creates the next version', $view);

        $dates = app(PreviousBcsDateNormalizer::class);

        $this->assertSame('1995-10-21', $dates->bDate('211095')['date']);
        $this->assertSame('1995-10-21', $dates->bDate('21101995')['date']);
        $this->assertSame('1995-10-21', $dates->optionalDob('10/21/1995')['date']);
        $this->assertNull($dates->optionalDob('')['error']);
        $this->assertNotNull($dates->bDate('321399')['error']);
    }

    public function test_repository_ui_uses_json_polling_and_is_not_examination_scoped(): void
    {
        $show = file_get_contents(resource_path('views/previous-bcs-repository/show.blade.php'));
        $routes = file_get_contents(base_path('routes/previous-bcs-repository.php'));

        $this->assertStringContainsString('fetch(url', $show);
        $this->assertStringContainsString('window.setInterval(poll,1500)', $show);
        $this->assertStringNotContainsString('EnsureExaminationSelected', $routes);
        $this->assertStringNotContainsString('ConfigureExaminationConnection', $routes);
        $this->assertStringContainsString('Repository Authority', $show);
        $this->assertStringContainsString('Approve & Make Effective', $show);
    }
}
