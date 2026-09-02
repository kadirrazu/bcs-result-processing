<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

class AllocationA1SeatBreakupBengaliHeaderSpacingHotfixContractTest extends TestCase
{
    public function test_pdf_does_not_force_bengali_cadre_title_to_bold_font_face(): void
    {
        $view = file_get_contents(resource_path('views/reports/pdf/allocation-seat-breakup.blade.php'));

        $this->assertStringContainsString('class="bn-title">{{ $entry->cadre_name_bn_snapshot }}', $view);
        $this->assertStringContainsString('font-weight:normal;', $view);
        $this->assertStringNotContainsString('<strong>{{ $entry->cadre_name_bn_snapshot }}</strong>', $view);
    }

    public function test_pdf_headers_are_uppercase_human_readable_and_rows_have_more_padding(): void
    {
        $view = file_get_contents(resource_path('views/reports/pdf/allocation-seat-breakup.blade.php'));

        $this->assertStringContainsString('CADRE TITLE / SUB CADRE TITLE', $view);
        $this->assertStringContainsString('SUB CADRE CODE', $view);
        $this->assertStringContainsString('TOTAL POST', $view);
        $this->assertStringContainsString('MQ POST', $view);
        $this->assertStringNotContainsString('sub_cadre_title', $view);
        $this->assertStringContainsString('padding:1.55mm .80mm;', $view);
    }

    public function test_browser_table_uses_same_header_labels_and_roomier_rows(): void
    {
        $view = file_get_contents(resource_path('views/allocation/seat-breakup-show.blade.php'));

        $this->assertStringContainsString('CADRE TITLE / SUB CADRE TITLE', $view);
        $this->assertStringContainsString('CADRE CODE', $view);
        $this->assertStringContainsString('SUB CADRE CODE', $view);
        $this->assertStringContainsString('allocation-seat-breakup-table', $view);
        $this->assertStringContainsString('padding:.70rem .78rem', $view);
    }
}
