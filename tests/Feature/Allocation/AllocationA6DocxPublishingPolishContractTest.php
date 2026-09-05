<?php

namespace Tests\Feature\Allocation;

use Tests\TestCase;

class AllocationA6DocxPublishingPolishContractTest extends TestCase
{
    public function test_docx_page_has_latest_placeholder_table_sample_download_and_default_eight(): void
    {
        $view = file_get_contents(resource_path('views/allocation/a6/docx.blade.php'));
        $index = file_get_contents(resource_path('views/allocation/a6/index.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/AllocationA6Controller.php'));
        $job = file_get_contents(app_path('Jobs/ProcessAllocationA6Export.php'));
        $routes = file_get_contents(base_path('routes/allocation.php'));

        self::assertStringContainsString('Download Sample DOCX Template', $view);
        self::assertStringContainsString('<th class="text-start">Purpose</th>', $view);
        self::assertStringContainsString('<th class="text-start">Placeholder</th>', $view);
        self::assertStringContainsString('Cadre registrations', $view);
        self::assertStringContainsString('<strong>[[110_ADMN]]</strong>', $view);
        self::assertStringContainsString('<strong>[[TOTAL_110_ADMN]]</strong>', $view);
        self::assertStringNotContainsString('নির্দিষ্ট ক্যাডার', $view);
        self::assertStringContainsString("'defaultPerLine' => 8", $controller);
        self::assertStringContainsString('value="8"', $index);
        self::assertStringContainsString("['registrations_per_line'] ?? 8", $job);
        self::assertStringContainsString("name('a6.docx.sample')", $routes);
    }

    public function test_docx_total_placeholders_and_sample_template_follow_latest_hierarchy_contract(): void
    {
        $export = file_get_contents(app_path('Services/Allocation/AllocationA6ExportService.php'));
        $sample = file_get_contents(app_path('Services/Allocation/AllocationA6DocxSampleTemplateService.php'));

        self::assertStringContainsString("\$replacements['TOTAL_'.\$key] = 'TOTAL = '.number_format(\$regs->count());", $export);
        self::assertStringContainsString("\$replacements['TOTAL_ALLOCATED'] = 'TOTAL = '.number_format(\$allRegs->count());", $export);
        self::assertStringContainsString('ক) সাধারণ ক্যাডারসমূহ ও ক্যাডারের পদসমূহঃ', $sample);
        self::assertStringContainsString('খ) প্রফেশনাল বা টেকনিক্যাল ক্যাডারসমূহ/ক্যাডারের প্রফেশনাল বা টেকনিক্যাল পদসমূহঃ', $sample);
        self::assertStringContainsString("->groupBy('serial')", $sample);
        self::assertStringContainsString("['sub_serial']", $sample);
        self::assertStringContainsString("'.'.\$this->banglaNumber(\$subSerial).'। '", $sample);
        self::assertStringContainsString('<w:vAlign w:val="top"/>', $sample);
        // Latest UI contract is specifically that sub-serial labels/tags are emitted with zero indent.
        // The generic paragraph helper may still retain optional indent support for unrelated future use.
        self::assertStringContainsString("\$this->paragraph(\$subLabel, true, 21, 'left');", $sample);
        self::assertStringContainsString("\$this->placeholderParagraphs(\$row, 0);", $sample);
        self::assertStringContainsString('cadre_name_bn_snapshot', $sample);
        self::assertStringContainsString('post_name_bn_snapshot', $sample);
        self::assertStringContainsString("now()->format('Ymd-His')", $sample);
        self::assertStringContainsString("'Nikosh'", $sample);
        self::assertStringContainsString("'Times New Roman'", $sample);
        self::assertStringContainsString('NO ALLOCATABLE CANDIDATE WAS LEFT FOR THIS POST', $export);
    }
}
