<?php

namespace App\Reports\Pdf;

use App\Reports\Shared\BanglaPdfFontResolver;
use App\Services\Circular\CircularFinalizedDatasetService;
use App\Support\Examinations\ExaminationContext;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class CircularFinalSummaryPdfReport
{
    public function __construct(
        private readonly BanglaPdfFontResolver $fontResolver,
        private readonly CircularFinalizedDatasetService $dataset,
        private readonly ExaminationContext $examinationContext,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(): array
    {
        $version = $this->dataset->finalizedVersion();
        $entries = $this->dataset->entries();
        $summary = $this->dataset->summary();
        $exam = $this->examinationContext->current();
        $examName = $exam?->name ?: (($exam?->bcs_number ? $exam->bcs_number.' BCS Examination' : null) ?: 'BCS Examination');

        $font = $this->fontResolver->resolve();
        $defaults = (new ConfigVariables)->getDefaults();
        $fontDefaults = (new FontVariables)->getDefaults();
        $fontDirs = $defaults['fontDir'];
        if (is_string($font['directory']) && $font['directory'] !== '') {
            $fontDirs[] = $font['directory'];
        }

        $fontData = ['R' => $font['regular'], 'useOTL' => 0x80];
        if (is_string($font['bold']) && $font['bold'] !== '') {
            $fontData['B'] = $font['bold'];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 14,
            'margin_bottom' => 13,
            'margin_header' => 5,
            'margin_footer' => 5,
            'tempDir' => $this->tempDirectory(),
            'fontDir' => array_values(array_unique($fontDirs)),
            'fontdata' => array_replace($fontDefaults['fontdata'], [$font['family'] => $fontData]),
            'default_font' => $font['family'],
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);

        $mpdf->SetTitle("Final Circular Summary - Version {$version}");
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLFooter('<div style="border-top:0.2mm solid #cfd6df;padding-top:1.5mm;font-size:8pt;color:#667085"><table width="100%"><tr><td>Finalized Circular report</td><td style="text-align:right">Page {PAGENO} of {nbpg}</td></tr></table></div>');

        $html = view('reports.pdf.circular-final-summary', [
            'entries' => $entries,
            'summary' => $summary,
            'version' => $version,
            'examName' => $examName,
            'banglaFontFamily' => $font['family'],
            'generatedAt' => now(),
        ])->render();

        $mpdf->WriteHTML($html);

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => "circular-final-summary-v{$version}-".now()->format('Ymd-His').'.pdf',
        ];
    }

    private function tempDirectory(): string
    {
        $path = storage_path('app/private/mpdf');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create mPDF temp directory [{$path}].");
        }

        return $path;
    }
}
