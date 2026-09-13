<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryBuilderAdvancedInteractionContractTest extends TestCase
{
    public function test_conditions_are_recursive_semantic_groups_with_and_or_logic(): void
    {
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));
        $config = file_get_contents(config_path('dynamic-reports.php'));

        self::assertStringContainsString('applyConditionGroup', $compiler);
        self::assertStringContainsString('conditionFieldIds', $compiler);
        self::assertStringContainsString("['and', 'or']", $compiler);
        self::assertStringContainsString("'max_condition_depth' => 4", $config);
        self::assertStringContainsString('+ Group', $view);
        self::assertStringContainsString('Match', $view);
        self::assertStringContainsString('ALL', $view);
        self::assertStringContainsString('ANY', $view);
    }

    public function test_builder_supports_multiple_group_and_sort_rules_with_explicit_priority(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        self::assertStringContainsString('Add Group Field', $view);
        self::assertStringContainsString('dq-group-field', $view);
        self::assertStringContainsString('Add Sort', $view);
        self::assertStringContainsString('Sorting Priority', $view);
        self::assertStringContainsString('Sort rules are applied from top to bottom.', $view);
        self::assertStringContainsString('dq-sort-priority', $view);
        self::assertStringContainsString("config('dynamic-reports.max_sorts', 5)", $compiler);
        self::assertStringContainsString("config('dynamic-reports.max_groups', 5)", $compiler);
    }

    public function test_browser_still_receives_no_internal_database_expression_metadata(): void
    {
        $registry = file_get_contents(app_path('Services/Reporting/DynamicQuery/SemanticFieldRegistry.php'));
        $browserMethod = substr($registry, strpos($registry, 'public function browserFields'));

        self::assertStringNotContainsString("'expression' =>", $browserMethod);
        self::assertStringNotContainsString("'source' =>", $browserMethod);
    }
}
