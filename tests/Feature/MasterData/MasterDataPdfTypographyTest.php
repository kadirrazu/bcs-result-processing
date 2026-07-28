<?php

namespace Tests\Feature\MasterData;

use Tests\TestCase;

final class MasterDataPdfTypographyTest extends TestCase
{
    public function test_pdf_report_uses_mpdf_and_complex_script_otl_profile(): void
    {
        $report = file_get_contents(app_path('Reports/Pdf/MasterDataPdfReport.php'));
        $this->assertIsString($report);
        $this->assertStringContainsString('use Mpdf\\Mpdf;', $report);
        $this->assertStringContainsString("'useOTL' => 0x80", $report);
        $this->assertStringContainsString('array_replace', $report);
        $this->assertStringContainsString('SetHTMLHeader', $report);
        $this->assertStringContainsString('SetHTMLFooter', $report);
        $this->assertStringContainsString(
            "'margin_top' => \$theme->number('page.margin_top_mm')",
            $report
        );
        $this->assertStringContainsString('Page {PAGENO} of {nbpg}', $report);
    }

    public function test_pdf_template_uses_thirteen_point_bangla_and_content_widths(): void
    {
        $template = file_get_contents(resource_path('views/reports/pdf/master-data.blade.php'));
        $this->assertIsString($template);
        $this->assertStringContainsString("font-family: '{{ \$banglaFontFamily }}'", $template);
        $this->assertStringContainsString(
            "font-size: {{ \$theme->number('fonts.bangla_size_pt') }}pt;",
            $template
        );
        $this->assertStringContainsString('.w-title-bn { width: 29%; }', $template);
        $this->assertStringContainsString('display: table-header-group', $template);
    }

    public function test_font_resolver_uses_bundled_freeserif_and_skips_nirmala(): void
    {
        $resolver = file_get_contents(app_path('Reports/Shared/BanglaPdfFontResolver.php'));
        $this->assertIsString($resolver);
        $this->assertStringContainsString("'FreeSerif.ttf'", $resolver);
        $this->assertStringContainsString("'FreeSerifBold.ttf'", $resolver);
        $this->assertStringContainsString("str_starts_with(strtolower(pathinfo(\$path, PATHINFO_FILENAME)), 'nirmala')", $resolver);
        $this->assertStringContainsString("str_contains(\$contents, 'GSUB')", $resolver);
        $this->assertStringContainsString("str_contains(\$contents, 'GPOS')", $resolver);
    }
}
