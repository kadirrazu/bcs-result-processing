<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA6LegacyDbfExportContractTest extends TestCase
{
    public function test_a6_exposes_two_independent_queued_dbf_exports_with_scope_specific_contracts(): void
    {
        $routes = file_get_contents(base_path('routes/allocation.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));
        $service = file_get_contents(app_path('Services/Allocation/AllocationA6DbfExportService.php'));
        $view = file_get_contents(resource_path('views/allocation/a6/index.blade.php'));

        self::assertStringContainsString('/a6/exports/dbf', $routes);
        self::assertStringContainsString('startDbf', $routes);
        self::assertStringContainsString("'scope' => ['required', 'in:tabulated,allocated']", $controller);
        self::assertStringContainsString("'field_count' => \$scope === 'tabulated' ? 19 : 17", $controller);
        self::assertStringContainsString("'DBF' => \$this->generateDbf", $job);
        self::assertStringContainsString("'application/x-dbf'", $job);

        foreach ([
            'REG', 'USER_ID', 'CATEGORY', 'TRACK', 'CFF', 'EM', 'PHC',
            'GEN_TOTAL', 'TECH_TOTAL', 'COM_MERIT', 'GEN_MERIT', 'TECH_MERIT',
            'ALOC_CH_CD', 'ALOC_CH_AB', 'CADRE_CODE', 'CADRE_ABBR', 'ALOC_BASIS',
        ] as $field) {
            self::assertStringContainsString("'name' => '{$field}'", $service);
        }

        self::assertStringContainsString("['name' => 'WITHHELD', 'type' => 'C', 'length' => 4]", $service);
        self::assertStringContainsString("['name' => 'CANCELLED', 'type' => 'C', 'length' => 4]", $service);
        self::assertStringContainsString("if (strtolower(trim(\$scope)) === 'tabulated')", $service);
        self::assertStringContainsString("'WITHHELD' => \$withheld ? 'TRUE' : ''", $service);
        self::assertStringContainsString("'CANCELLED' => \$cancelled ? 'TRUE' : ''", $service);
        self::assertStringContainsString('dispositionMap', $service);
        self::assertStringContainsString('applyPublishedOnly', $service);
        self::assertStringContainsString('ChoiceOptimizationHistoricalChoice', $service);
        self::assertStringContainsString('final_choice_codes', $service);
        self::assertStringContainsString('Tabulated 19 fields', $view);
        self::assertStringContainsString('Allocated 17 fields', $view);
        self::assertStringContainsString('blank otherwise', $view);
    }

    public function test_dbf_writer_is_native_and_does_not_require_php_dbase_extension(): void
    {
        $writer = file_get_contents(app_path('Services/Reporting/DbfReportWriter.php'));

        self::assertStringContainsString('chr(0x03)', $writer);
        self::assertStringContainsString('fwrite($handle, "\\x0D")', $writer);
        self::assertStringContainsString('fwrite($handle, "\\x1A")', $writer);
        self::assertStringNotContainsString('dbase_create', $writer);
        self::assertStringNotContainsString('dbase_add_record', $writer);
    }
}
