<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

class AllocationA1SeatBreakupPortraitPdfUiPolishContractTest extends TestCase
{
    public function test_seat_breakup_pdf_is_a4_portrait_with_preliminary_style_header_footer(): void
    {
        $source = file_get_contents(app_path('Reports/Pdf/AllocationSeatBreakupPdfReport.php'));

        $this->assertStringContainsString("'format' => 'A4'", $source);
        $this->assertStringContainsString("'orientation' => 'P'", $source);
        $this->assertStringContainsString("'margin_top' => 28", $source);
        $this->assertStringContainsString('SetHTMLHeader($this->headerHtml(', $source);
        $this->assertStringContainsString('<strong>EXAM TITLE:</strong>', $source);
        $this->assertStringContainsString('<strong>REPORT TITLE:</strong>', $source);
        $this->assertStringContainsString('<strong>REPORT GENERATED ON:</strong>', $source);
        $this->assertStringContainsString('Page {PAGENO} of {nbpg}', $source);
    }

    public function test_seat_breakup_pdf_keeps_bengali_on_registered_font(): void
    {
        $source = file_get_contents(app_path('Reports/Pdf/AllocationSeatBreakupPdfReport.php'));
        $view = file_get_contents(resource_path('views/reports/pdf/allocation-seat-breakup.blade.php'));

        $this->assertStringContainsString("'autoScriptToLang' => true", $source);
        $this->assertStringContainsString("'autoLangToFont' => false", $source);
        $this->assertStringContainsString("font-family:'{{ \$banglaFontFamily }}'", $view);
        $this->assertStringContainsString('cadre_name_bn_snapshot', $view);
        $this->assertStringContainsString('post_name_bn_snapshot', $view);
    }

    public function test_seat_breakup_screen_centers_all_columns_except_title_column(): void
    {
        $view = file_get_contents(resource_path('views/allocation/seat-breakup-show.blade.php'));

        $this->assertStringContainsString('<th class="text-center">SL</th>', $view);
        $this->assertStringContainsString('<th>CADRE TITLE / SUB CADRE TITLE</th>', $view);
        $this->assertStringContainsString('<th class="text-center">CADRE CODE</th>', $view);
        $this->assertStringContainsString('<td class="text-center">{{ $row->sl }}</td>', $view);
        $this->assertStringContainsString('<td class="text-center">{{ number_format($row->total_post) }}</td>', $view);
    }
}
