<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA6AllMeritTechExportContractTest extends TestCase
{
    public function test_all_merit_tech_is_selectable_and_exported_in_a6_excel_builder(): void
    {
        $catalog = file_get_contents(app_path('Services/Allocation/AllocationA6ExcelFieldCatalog.php'));
        $export = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));

        self::assertStringContainsString("'merit.all_merit_tech'=>'all_merit_tech'", $catalog);
        self::assertStringContainsString("'merit.all_merit_tech' => MeritResult::allMeritTechJson(\$merit?->all_merit_tech)", $export);
    }

    public function test_both_dbf_exports_include_all_merit_tech_without_changing_disposition_contract(): void
    {
        $service = file_get_contents(app_path('Services/Allocation/AllocationA6DbfExportService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $view = file_get_contents(resource_path('views/allocation/a6/index.blade.php'));

        self::assertStringContainsString("'ALLM_TECH' => MeritResult::allMeritTechJson(\$merit?->all_merit_tech)", $service);
        self::assertStringContainsString("['name' => 'ALLM_TECH', 'type' => 'C', 'length' => 254]", $service);
        self::assertStringContainsString("'field_count' => \$scope === 'tabulated' ? 20 : 18", $controller);
        self::assertStringContainsString('Tabulated 20 fields', $view);
        self::assertStringContainsString('Allocated 18 fields', $view);
        self::assertStringContainsString('ALLM_TECH', $view);

        self::assertStringContainsString("if (strtolower(trim(\$scope)) === 'tabulated')", $service);
        self::assertStringContainsString("['name' => 'WITHHELD', 'type' => 'C', 'length' => 4]", $service);
        self::assertStringContainsString("['name' => 'CANCELLED', 'type' => 'C', 'length' => 4]", $service);
    }
}
