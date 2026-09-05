<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\TestCase;

class AllocationA1SeatBreakupPdfBanglaRemarksHotfixContractTest extends TestCase
{
    public function test_pdf_keeps_bengali_on_the_explicit_registered_font(): void
    {
        $source = file_get_contents(app_path('Reports/Pdf/AllocationSeatBreakupPdfReport.php'));

        // Latest committed PDF polish deliberately disables automatic language-font
        // switching because Bengali is rendered with the explicitly registered font.
        $this->assertStringContainsString("'autoScriptToLang' => true", $source);
        $this->assertStringContainsString("'autoLangToFont' => false", $source);
        $this->assertStringNotContainsString("'autoLangToFont' => true", $source);
    }

    public function test_pdf_template_uses_explicit_bengali_font_class_and_keeps_remarks_blank(): void
    {
        $source = file_get_contents(resource_path('views/reports/pdf/allocation-seat-breakup.blade.php'));

        $this->assertStringContainsString('.bn {', $source);
        $this->assertStringContainsString('cadre_name_bn_snapshot', $source);
        $this->assertStringContainsString('post_name_bn_snapshot', $source);
        $this->assertStringContainsString('<td></td>', $source);
        $this->assertStringNotContainsString('$entry?->note', $source);
    }
}
