<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

final class NonCadreChoiceNc3R2ContractTest extends TestCase
{
    public function test_nc3_r2_queue_template_pagination_filters_details_and_manual_toggle_contract(): void
    {
        $routes=file_get_contents(base_path('routes/non-cadre.php'));
        $controller=file_get_contents(app_path('Http/Controllers/NonCadre/Choice/NonCadreChoiceController.php'));
        $service=file_get_contents(app_path('Services/NonCadre/Choice/NonCadreChoiceService.php'));
        $job=file_get_contents(app_path('Jobs/ProcessNonCadreChoiceImport.php'));
        $version=file_get_contents(resource_path('views/non-cadre/choice/version.blade.php'));
        $detail=file_get_contents(resource_path('views/non-cadre/choice/show.blade.php'));
        $config=file_get_contents(config_path('non-cadre.php'));

        self::assertStringContainsString("/template",$routes);
        self::assertStringContainsString("/progress",$routes);
        self::assertStringContainsString("/item/{item}",$routes);
        self::assertStringContainsString("/choice/{code}",$routes);
        self::assertStringContainsString('ProcessNonCadreChoiceImport::dispatch',$service);
        self::assertStringContainsString('implements ShouldQueue',$job);
        self::assertStringContainsString("paginate(20)",$service);
        self::assertStringContainsString("'adjusted'",$service);
        self::assertStringContainsString("'CADRE_ALLOCATED_HISTORICAL_ONLY'",$service);
        self::assertStringContainsString('Download Sample Template',file_get_contents(resource_path('views/non-cadre/choice/index.blade.php')));
        self::assertStringContainsString('Non-Cadre Allocation Eligible',$version);
        self::assertStringContainsString('Manually Adjusted',$version);
        self::assertStringContainsString('Cadre Allocated — Historical Only',$version);
        self::assertStringContainsString('Empty Choice',$version);
        self::assertStringContainsString('Candidate Choice Details',$detail);
        self::assertStringContainsString('Validation Reason',$detail);
        self::assertStringContainsString('Exclude',$detail);
        self::assertStringContainsString('Restore',$detail);
        self::assertStringNotContainsString('Save Effective Choice',$detail);
        self::assertStringContainsString("'queue' => env('NON_CADRE_CHOICE_QUEUE', 'imports')",$config);
        self::assertStringContainsString('progress_percent',$controller);
    }

    public function test_nc3_queue_progress_migration_is_exam_scoped(): void
    {
        $migration=file_get_contents(database_path('examination-migrations/2026_09_20_220500_add_non_cadre_choice_queue_progress.php'));
        self::assertStringContainsString("Schema::connection('exam')->table('non_cadre_choice_imports'",$migration);
        self::assertStringContainsString("'stored_filename'",$migration);
        self::assertStringContainsString("'processed_rows'",$migration);
        self::assertStringContainsString("'progress_percent'",$migration);
        self::assertStringContainsString("'failure_message'",$migration);
    }
}
