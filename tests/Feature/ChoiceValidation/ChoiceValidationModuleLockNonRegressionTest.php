<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationModuleLockNonRegressionTest extends TestCase
{
    public function test_cv6_cv7_do_not_define_upstream_module_schema_or_routes(): void
    {
        $migration = file_get_contents(
            database_path('examination-migrations/2026_08_12_031500_create_choice_validation_finalization.php')
        );
        $routes = file_get_contents(base_path('routes/choice-validation.php'));

        foreach ([
            'registrations',
            'preliminary_results',
            'written_results',
            'viva_results',
            'circular_entries',
        ] as $upstreamTable) {
            self::assertStringNotContainsString("Schema::create('{$upstreamTable}'", $migration);
            self::assertStringNotContainsString("Schema::table('{$upstreamTable}'", $migration);
        }

        self::assertStringNotContainsString("prefix('preliminary')", $routes);
        self::assertStringNotContainsString("prefix('written')", $routes);
        self::assertStringNotContainsString("prefix('viva')", $routes);
        self::assertStringNotContainsString("prefix('circular')", $routes);
    }
}
