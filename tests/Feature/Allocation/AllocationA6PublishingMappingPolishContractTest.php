<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

final class AllocationA6PublishingMappingPolishContractTest extends TestCase
{
    public function test_txt_docx_excel_and_export_run_polish_contracts_are_present(): void
    {
        $export = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));
        $catalog = file_get_contents(app_path('Services/Allocation/AllocationA6ExcelFieldCatalog.php'));
        $sample = file_get_contents(app_path('Services/Allocation/AllocationA6DocxSampleTemplateService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $builder = file_get_contents(resource_path('views/allocation/a6/excel-builder.blade.php'));
        $runView = file_get_contents(resource_path('views/allocation/a6/export-show.blade.php'));

        foreach (['cadre_name_snapshot', 'post_name_snapshot', "'#'.\$code", "str_repeat('-', 72)"] as $needle) {
            self::assertStringContainsString($needle, $export);
        }
        self::assertStringContainsString("\$lines[] = '';\n            \$lines[] = '';", $export);

        self::assertStringContainsString('NO ALLOCATABLE CANDIDATE WAS LEFT FOR THIS POST', $export);
        self::assertStringContainsString("'Nikosh'", $sample);
        self::assertStringContainsString("'Times New Roman'", $sample);
        self::assertStringContainsString('$isBangla ? 24 : 22', $sample);

        foreach ([
            "'registration.sex_code'=>'Sex Code'",
            "'registration.sex' => 'Sex'",
            "'registration.district_code'=>'District Code'",
            "'registration.district_name' => 'District Name'",
            "'choice.registration_abbr' => 'Registration Choice Abbreviations'",
            "'choice.validated_abbr' => 'Validated Choice Abbreviations'",
            "'choice.omr_abbr' => 'OMR Choice Abbreviations'",
            "'choice.effective_abbr' => 'Effective Choice Abbreviations'",
            "'allocation.cadre_abbr' => 'Cadre Abbreviation'",
        ] as $needle) {
            self::assertStringContainsString($needle, $catalog);
        }
        self::assertStringContainsString('expandSelection', $catalog);
        self::assertStringContainsString('uppercaseText', $export);
        self::assertStringContainsString("Gender::query()->whereIn('code'", $export);
        self::assertStringContainsString("District::query()->whereIn('code'", $export);
        self::assertStringContainsString('$this->reports->abbreviations', $export);

        self::assertStringContainsString('Select Group', $builder);
        self::assertStringContainsString('Clear Group', $builder);
        self::assertStringContainsString('a6-group-clear', $builder);
        self::assertStringContainsString('Mapped companion columns are added automatically', $builder);

        self::assertStringContainsString('User::query()->find', $controller);
        self::assertStringContainsString('$run->generated_by }} - {{ $generatedByUser?->name', $runView);
        self::assertStringContainsString('Back to A6 — Reporting &amp; Export', $runView);
    }
}
