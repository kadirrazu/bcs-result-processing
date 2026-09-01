<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

class AllocationA1SeatBreakupPdfBanglaRemarksHotfixContractTest extends TestCase
{
    public function test_pdf_enables_automatic_bengali_font_selection(): void
    {
        $source = file_get_contents(app_path('Reports/Pdf/AllocationSeatBreakupPdfReport.php'));

        $this->assertStringContainsString("'autoLangToFont' => true", $source);
        $this->assertStringNotContainsString("'autoLangToFont' => false", $source);
    }

    public function test_pdf_template_uses_explicit_bengali_font_class_and_keeps_remarks_blank(): void
    {
        $source = file_get_contents(resource_path('views/reports/pdf/allocation-seat-breakup.blade.php'));

        $this->assertStringContainsString('.bn {', $source);
        $this->assertStringContainsString('cadre_name_bn_snapshot', $source);
        $this->assertStringContainsString('post_name_bn_snapshot', $source);
        $this->assertStringNotContainsString("$entry?->note ?: '—'", $source);
    }
}
