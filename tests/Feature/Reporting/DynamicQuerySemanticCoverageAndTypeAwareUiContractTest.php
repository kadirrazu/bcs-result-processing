<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQuerySemanticCoverageAndTypeAwareUiContractTest extends TestCase
{
    public function test_step_one_shows_field_name_followed_by_uppercase_type(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString("const typeLabel=t=>String(t||'string').replace(/[-_]+/g,' ').toUpperCase()", $view);
        self::assertStringContainsString('${esc(f.label)} (${esc(typeLabel(f.type))})', $view);
        self::assertStringContainsString('${esc(f.module)} · ${esc(f.label)} (${esc(typeLabel(f.type))})', $view);
    }

    public function test_condition_inputs_are_type_aware_and_enum_in_uses_approved_multi_select(): void
    {
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString("f.type==='date'?'date':(f.type==='number'?'number':'text')", $view);
        self::assertStringContainsString('multiple size=', $view);
        self::assertStringContainsString('selectedOptions', $view);
        self::assertStringContainsString('Ctrl/Command-click to select multiple values.', $view);
    }

    public function test_semantic_registry_expands_to_finalized_preliminary_written_viva_and_tabulation_sources(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));
        $authority = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicReportAuthority.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        foreach ([
            "'preliminary.mark'", "'preliminary.result_status'",
            "'written.qualified_track'", "'written.general_total'",
            "'viva.mark'", "'viva.result_status'",
            "'tabulation.general_grand_total'", "'tabulation.technical_merit_eligible'",
        ] as $field) {
            self::assertStringContainsString($field, $config);
        }

        self::assertStringContainsString('PreliminaryProcessingState::query()->find(1)', $authority);
        self::assertStringContainsString('WrittenProcessingState::query()->find(1)', $authority);
        self::assertStringContainsString('VivaProcessingState::query()->find(1)', $authority);
        self::assertStringContainsString('TabulationProcessingState::query()->find(1)', $authority);
        self::assertStringContainsString("leftJoin('preliminary_results'", $compiler);
        self::assertStringContainsString("leftJoin('written_results'", $compiler);
        self::assertStringContainsString("leftJoin('viva_results'", $compiler);
        self::assertStringContainsString("leftJoin('tabulation_results'", $compiler);
        self::assertStringContainsString('assertSourcesReady', $compiler);
    }
}
