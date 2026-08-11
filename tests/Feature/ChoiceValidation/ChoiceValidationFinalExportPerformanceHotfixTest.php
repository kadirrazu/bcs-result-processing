<?php

namespace Tests\Feature\ChoiceValidation;

use Tests\TestCase;

final class ChoiceValidationFinalExportPerformanceHotfixTest extends TestCase
{
    public function test_pdf_summary_does_not_load_entire_finalized_candidate_dataset(): void
    {
        $pdf = file_get_contents(
            app_path('Reports/Pdf/ChoiceValidationFinalSummaryPdfReport.php')
        );

        self::assertStringContainsString('verifiedSummary()', $pdf);
        self::assertStringContainsString("selectRaw('status, COUNT(*) as aggregate')", $pdf);
        self::assertStringNotContainsString('$this->dataset->results()', $pdf);
        self::assertStringNotContainsString('$this->dataset->finalizedVersion()', $pdf);
    }

    public function test_excel_uses_flat_chunked_queries_without_source_item_eager_loading(): void
    {
        $excel = file_get_contents(
            app_path('Reports/Excel/ChoiceValidationFinalSummaryExcelReport.php')
        );

        self::assertStringContainsString('verifiedSummary()', $excel);
        self::assertStringContainsString("chunkById(", $excel);
        self::assertStringContainsString("choice_validation_sources as src", $excel);
        self::assertStringContainsString('source_snapshot', $excel);
        self::assertStringNotContainsString('$this->dataset->results()', $excel);
        self::assertStringNotContainsString("with(['registration', 'source.items'])", $excel);
        self::assertStringNotContainsString('setAutoSize(true)', $excel);
        self::assertStringContainsString('setPreCalculateFormulas(false)', $excel);
    }

    public function test_export_path_has_single_verified_summary_entry_point(): void
    {
        $dataset = file_get_contents(
            app_path('Services/ChoiceValidation/ChoiceValidationFinalizedDatasetService.php')
        );

        self::assertStringContainsString('public function verifiedSummary(): array', $dataset);
        self::assertStringContainsString('public function verifiedFinalizationRun()', $dataset);
        self::assertStringContainsString('$this->assertReady()', $dataset);
    }

    public function test_effective_corrected_choices_do_not_show_redundant_retained_text(): void
    {
        $view = file_get_contents(
            resource_path('views/choice-validation/result-detail.blade.php')
        );

        $start = strpos($view, 'Effective Choices After Manual Correction');
        $end = strpos($view, 'Validated Choices', $start);

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $section = substr($view, $start, $end - $start);

        self::assertStringNotContainsString('>Retained<', $section);
        self::assertStringContainsString('Removed', $section);
        self::assertStringContainsString('Expanded', $section);
    }
}
