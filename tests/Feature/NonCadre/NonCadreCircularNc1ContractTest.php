<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

final class NonCadreCircularNc1ContractTest extends TestCase
{
    public function test_nc1_circular_contract_is_isolated_and_complete(): void
    {
        $root = base_path();
        $routes = file_get_contents($root.'/routes/non-cadre.php');
        $validator = file_get_contents($root.'/app/Services/NonCadre/Circular/NonCadreCircularValidator.php');
        $sheet = file_get_contents($root.'/app/Services/NonCadre/Circular/NonCadreCircularSpreadsheetService.php');
        $workflow = file_get_contents($root.'/app/Services/NonCadre/Circular/NonCadreCircularWorkflowService.php');
        $migration = file_get_contents($root.'/database/examination-migrations/2026_09_18_234500_add_non_cadre_circular_import_workflow.php');
        $foundationMigration = file_get_contents($root.'/database/examination-migrations/2026_09_18_230000_create_non_cadre_processing_foundation.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/NonCadre/Circular/NonCadreCircularController.php');
        $versionView = file_get_contents($root.'/resources/views/non-cadre/circular/version.blade.php');

        self::assertStringContainsString("prefix('circular')", $routes);
        self::assertStringContainsString("'post_grade','post_serial','post_sub_serial','ministry','ministry_bn','entity','entity_bn','post_title','post_title_bn','post_code','post_count','bachelor_subject_codes','status','special_requirement','special_requirement_note'", $sheet);
        self::assertStringContainsString("Unknown bachelor subject code(s)", $validator);
        self::assertStringContainsString("special_requirement_note is required", $validator);
        self::assertStringContainsString("status must be ACTIVE", $validator);
        self::assertStringContainsString("Duplicate post_code", $sheet);
        self::assertStringContainsString("unique(['circular_version_id', 'post_code'], 'nc_circ_post_version_code_uq')", $foundationMigration);
        self::assertStringContainsString("->orderBy('post_grade')", $controller);
        self::assertStringContainsString("->orderBy('post_serial')", $controller);
        self::assertStringContainsString("->orderBy('post_sub_serial')", $controller);
        self::assertStringContainsString("str_replace('|', ', ', \$p->bachelor_subject_codes)", $versionView);
        self::assertStringContainsString("Schema::connection('exam')", $migration);
        self::assertStringContainsString("'seat_breakup_status' => 'stale'", $workflow);
        self::assertStringContainsString("'choice_status' => 'stale'", $workflow);
        self::assertStringContainsString("'allocation_status' => 'stale'", $workflow);
        self::assertStringContainsString("'reporting_status' => 'stale'", $workflow);
        self::assertStringNotContainsString('App\\Services\\Circular\\', $workflow);
    }
}
