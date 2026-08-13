<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritCadreReportingContractTest extends TestCase
{
    public function test_cadre_report_is_indexed_and_ordered_by_cadre_merit_position(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_08_14_005000_create_merit_generation_module.php'));
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $view = file_get_contents(resource_path('views/merit/cadre.blade.php'));

        $this->assertStringContainsString("index(['processing_run_id','cadre_code','source_merit_position']", $migration);
        $this->assertStringContainsString('where(\'merit_cadre_ranks.cadre_code\', $cadreCode)', $controller);
        $this->assertStringContainsString('orderBy(\'merit_cadre_ranks.cadre_merit_position\')', $controller);
        $this->assertStringContainsString("'m.all_merit_tech'", $controller);
        $this->assertStringContainsString('Cadre Merit', $view);
        $this->assertStringContainsString('Choice Position', $view);
        $this->assertStringContainsString('all_merit_tech', $view);
    }
}
