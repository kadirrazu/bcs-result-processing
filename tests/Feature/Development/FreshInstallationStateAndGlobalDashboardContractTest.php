<?php

namespace Tests\Feature\Development;

use Tests\TestCase;

final class FreshInstallationStateAndGlobalDashboardContractTest extends TestCase
{
    public function test_global_dashboard_no_longer_contains_bootstrap_placeholder_copy(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/index.blade.php'));

        self::assertStringContainsString('Global Dashboard', $view);
        self::assertStringContainsString('Global System Workspace', $view);
        self::assertStringContainsString("route('examinations.index')", $view);
        self::assertStringContainsString("route('previous-bcs-repository.index')", $view);
        self::assertStringContainsString("route('users.index')", $view);
        self::assertStringNotContainsString('Environment setup completed', $view);
        self::assertStringNotContainsString('The business modules have not been implemented yet.', $view);
    }

    public function test_workflow_change_migration_does_not_stale_pristine_choice_optimization_state(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_09_07_120000_make_choice_optimization_mandatory_and_add_track_audit.php'));

        self::assertStringContainsString("where('status', '<>', 'not_started')", $migration);
        self::assertStringContainsString("orWhereNotNull('dataset_hash')", $migration);
        self::assertStringContainsString("orWhereNotNull('source_snapshot')", $migration);
    }

    public function test_repair_migration_only_clears_the_known_false_stale_pristine_state(): void
    {
        $repair = file_get_contents(database_path('examination-migrations/2026_09_17_090000_repair_pristine_choice_optimization_stale_state.php'));

        self::assertStringContainsString("where('status', 'not_started')", $repair);
        self::assertStringContainsString("where('is_stale', true)", $repair);
        self::assertStringContainsString("whereNull('dataset_hash')", $repair);
        self::assertStringContainsString("whereNull('source_snapshot')", $repair);
        self::assertStringContainsString("'is_stale' => false", $repair);
        self::assertStringContainsString("'stale_reason' => null", $repair);
    }
}
