<?php

namespace App\Reports\Pdf;

use App\Models\PreliminaryDistributionReport;
use App\Reports\Themes\ReportTheme;
use App\Reports\Themes\ReportThemeManager;
use Illuminate\Support\Carbon;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/** Generate the internal Preliminary mark-distribution PDF from an immutable report snapshot. */
final class PreliminaryDistributionPdfReport
{
    public function __construct(private readonly ReportThemeManager $themeManager) {}

    /** @return array{content:string, filename:string} */
    public function generate(PreliminaryDistributionReport $report, string $examinationName): array
    {
        $generatedAt = now();
        $theme = $this->themeManager->default();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 28,
            'margin_bottom' => 15,
            'margin_header' => 6,
            'margin_footer' => 6,
            'tempDir' => $this->prepareTempDirectory(),
            'default_font' => $theme->string('fonts.english_family'),
        ]);

        $mpdf->SetTitle($examinationName.' - Preliminary Mark Distribution');
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLHeader($this->headerHtml($examinationName, $generatedAt, $theme));
        $mpdf->SetHTMLFooter($this->footerHtml($theme));

        $html = view('reports.pdf.preliminary-distribution', [
            'rows' => $report->distribution ?? [],
            'theme' => $theme,
        ])->render();

        $mpdf->WriteHTML($html);

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => 'preliminary-mark-distribution-'.$report->id.'-'.$generatedAt->format('Ymd-His').'.pdf',
        ];
    }

    private function headerHtml(string $examinationName, Carbon $generatedAt, ReportTheme $theme): string
    {
        $exam = e($examinationName);
        $timestamp = e($generatedAt->format('d M Y, h:i:s A'));
        $family = e($theme->string('fonts.english_family'));
        $text = e($theme->string('colors.text'));
        $muted = e($theme->string('colors.muted'));

        return <<<HTML
        <div style="text-align:center; font-family:{$family}, sans-serif; color:{$text}; line-height:1.35;">
            <div style="font-size:11pt;"><strong>EXAM TITLE:</strong> {$exam}</div>
            <div style="margin-top:1mm; font-size:11pt;"><strong>REPORT TITLE:</strong> PRELIMINARY MARK DISTRIBUTION</div>
            <div style="margin-top:1mm; font-size:9pt; color:{$muted};"><strong>REPORT GENERATED ON:</strong> {$timestamp}</div>
        </div>
        HTML;
    }

    private function footerHtml(ReportTheme $theme): string
    {
        $family = e($theme->string('fonts.english_family'));
        $footer = e($theme->string('colors.footer'));

        return <<<HTML
        <div style="text-align:right; font-family:{$family}, sans-serif; font-size:8pt; color:{$footer};">
            Page {PAGENO} of {nbpg}
        </div>
        HTML;
    }

    private function prepareTempDirectory(): string
    {
        $directory = storage_path('app/private/mpdf/preliminary-distribution');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create mPDF temporary directory [{$directory}].");
        }

        if (! is_writable($directory)) {
            throw new RuntimeException("mPDF temporary directory is not writable [{$directory}].");
        }

        return $directory;
    }
}
