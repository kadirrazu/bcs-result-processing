<?php

namespace Tests\Feature\Circular;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CircularC5PdfBanglaHeaderHotfixContractTest extends TestCase
{
    #[Test]
    public function authority_preview_uses_bengali_capable_default_font_and_exam_context(): void
    {
        $report = file_get_contents(app_path('Reports/Pdf/CircularAuthorityPreviewPdfReport.php'));

        $this->assertStringContainsString("'default_font' => \$font['family']", $report);
        $this->assertStringContainsString("'autoLangToFont' => true", $report);
        $this->assertStringContainsString('ExaminationContext', $report);
        $this->assertStringContainsString("'examName' => \$examName", $report);
        $this->assertStringContainsString("'reportTitle' => \$reportTitle", $report);
    }

    #[Test]
    public function authority_preview_header_contains_requested_fields_and_safe_english_labels(): void
    {
        $view = file_get_contents(resource_path('views/reports/pdf/circular-authority-preview.blade.php'));

        $this->assertStringContainsString('Exam Name:', $view);
        $this->assertStringContainsString('Report Title:', $view);
        $this->assertStringContainsString('Circular Version:', $view);
        $this->assertStringContainsString('A. General Cadres and Cadre Posts', $view);
        $this->assertStringContainsString('B. Professional / Technical Cadres and Cadre Posts', $view);
        $this->assertStringContainsString('Educational Subject Codes', $view);
        $this->assertStringContainsString('Written Post-Related Subject Codes', $view);
    }
}
