<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQuerySemanticInterpretationContractTest extends TestCase
{
    public function test_enum_values_are_formatted_for_human_readable_report_output(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));
        $formatter = file_get_contents(app_path('Services/Reporting/DynamicQuery/SemanticValueFormatter.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));

        self::assertStringContainsString("'1' => 'Male'", $config);
        self::assertStringContainsString("'2' => 'Female'", $config);
        self::assertStringContainsString("'3' => 'Third Gender'", $config);
        self::assertStringContainsString('formatRows', $formatter);
        self::assertStringContainsString('$this->formatter->formatRows($rows, $fields)', $compiler);
        self::assertStringContainsString('$this->formatter->formatRows($rows, $groups)', $compiler);
    }

    public function test_allocated_cadre_has_a_readable_semantic_field_without_removing_raw_code(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));
        $formatter = file_get_contents(app_path('Services/Reporting/DynamicQuery/SemanticValueFormatter.php'));

        self::assertStringContainsString("'allocation.cadre'", $config);
        self::assertStringContainsString("'label' => 'Allocated Cadre'", $config);
        self::assertStringContainsString("'formatter' => 'cadre'", $config);
        self::assertStringContainsString("'allocation.cadre_code'", $config);
        self::assertStringContainsString('CadreMaster::query()', $formatter);
        self::assertStringContainsString('CadreSubMaster::query()', $formatter);
        self::assertStringContainsString("return 'Not Allocated';", $formatter);
    }
}
