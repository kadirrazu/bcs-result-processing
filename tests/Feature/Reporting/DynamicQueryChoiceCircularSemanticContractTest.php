<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryChoiceCircularSemanticContractTest extends TestCase
{
    public function test_choice_fields_are_reduced_to_codes_and_counts_for_each_business_stage(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));

        foreach ([
            "'choice_validation.registration_choices'",
            "'choice_validation.registration_choice_count'",
            "'choice_validation.validated_choices'",
            "'choice_validation.validated_count'",
            "'choice_optimization.optimized_choices'",
            "'choice_optimization.optimized_count'",
            "'choice_optimization.removed_choices'",
            "'choice_optimization.removed_count'",
            "'choice_optimization.allocation_ready_choices'",
            "'choice_optimization.allocation_ready_count'",
            "'choice_optimization.final_allocation_ready_choices'",
            "'choice_optimization.final_count'",
        ] as $field) {
            self::assertStringContainsString($field, $config);
        }

        foreach ([
            "'choice_validation.status'",
            "'choice_validation.effective_track'",
            "'choice_optimization.effective_source'",
            "'choice_optimization.status'",
            "'choice_optimization.manual_adjustment_active'",
        ] as $removedField) {
            self::assertStringNotContainsString($removedField, $config);
        }

        self::assertStringContainsString("'type' => 'choice-list'", $config);
        self::assertStringContainsString("'contains_choice'", $config);
        self::assertStringContainsString("'contains_any'", $config);
        self::assertStringContainsString("'contains_all'", $config);
    }

    public function test_choice_membership_conditions_are_exact_and_final_choice_respects_manual_exclusions(): void
    {
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        self::assertStringContainsString('applyChoiceListCondition', $compiler);
        self::assertStringContainsString('FIND_IN_SET(?, COALESCE', $compiler);
        self::assertStringContainsString("JSON_SEARCH({\$column}, 'one', ?)", $compiler);
        self::assertStringContainsString('excluded_codes_csv', $compiler);
        self::assertStringContainsString("'final_json'", $compiler);
        self::assertStringContainsString('dynamic_registration_choices', $compiler);
    }

    public function test_circular_fields_are_focused_on_effective_code_name_and_type(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));

        foreach (["'circular.effective_code'", "'circular.effective_name'", "'circular.cadre_type'"] as $field) {
            self::assertStringContainsString($field, $config);
        }
        self::assertStringNotContainsString("'circular.allocated_serial'", $config);
        self::assertStringNotContainsString("'circular.allocated_special_requirement'", $config);
        self::assertStringContainsString("'lookup' => 'cadre_effective'", $config);
    }

    public function test_choice_and_circular_sources_require_current_finalized_authorities(): void
    {
        $authority = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicReportAuthority.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        self::assertStringContainsString('ChoiceValidationProcessingState::query()->find(1)', $authority);
        self::assertStringContainsString('ChoiceOptimizationProcessingState::query()->find(1)', $authority);
        self::assertStringContainsString('CircularProcessingState::query()->find(1)', $authority);
        self::assertStringContainsString('final_allocation_ready_choice_hash', $authority);
        self::assertStringContainsString('datasetHash((string) $state->dataset_hash)', $authority);

        self::assertStringContainsString("leftJoin('choice_validation_results'", $compiler);
        self::assertStringContainsString("leftJoin('choice_optimization_historical_choices'", $compiler);
        self::assertStringContainsString("leftJoinSub(\$activeManual, 'dynamic_manual_adjustments'", $compiler);
        self::assertStringContainsString("leftJoin('circular_entries'", $compiler);
        self::assertStringContainsString("in_array('circular', \$sources, true) && ! in_array('allocation', \$sources, true)", $compiler);
    }
}
