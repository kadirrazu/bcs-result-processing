<?php

namespace App\Reports\Pdf;

use App\Reports\Shared\BanglaPdfFontResolver;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Support\Facades\DB;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class ChoiceValidationFinalSummaryPdfReport
{
    public function __construct(
        private readonly BanglaPdfFontResolver $fontResolver,
        private readonly ChoiceValidationFinalizedDatasetService $dataset,
        private readonly ExaminationContext $examinationContext,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(): array
    {
        // One integrity scan only. PDF summary does not need 3,000+ candidate
        // rows or Registration/Source relations in memory.
        $summary = $this->dataset->verifiedSummary();
        $version = (int) $summary['validation_version'];

        $exam = $this->examinationContext->current();
        $examName = $exam?->name
            ?: (($exam?->bcs_number ? $exam->bcs_number.' BCS Examination' : null)
                ?: 'BCS Examination');

        $statusBreakdown = DB::connection('exam')
            ->table('choice_validation_results')
            ->where('validation_version', $version)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->pluck('aggregate', 'status');

        $font = $this->fontResolver->resolve();
        $defaults = (new ConfigVariables)->getDefaults();
        $fontDefaults = (new FontVariables)->getDefaults();
        $fontDirs = $defaults['fontDir'];

        if (is_string($font['directory']) && $font['directory'] !== '') {
            $fontDirs[] = $font['directory'];
        }

        $fontData = [
            'R' => $font['regular'],
            'useOTL' => 0x80,
        ];

        if (is_string($font['bold']) && $font['bold'] !== '') {
            $fontData['B'] = $font['bold'];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'tempDir' => $this->tempDirectory(),
            'fontDir' => array_values(array_unique($fontDirs)),
            'fontdata' => array_replace(
                $fontDefaults['fontdata'],
                [$font['family'] => $fontData]
            ),
            'default_font' => $font['family'],
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);

        $mpdf->SetTitle("Final Choice Validation Summary - Version {$version}");
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLFooter(
            '<div style="border-top:0.2mm solid #cfd6df;padding-top:1.5mm;font-size:8pt;color:#667085">'
            .'<table width="100%"><tr><td>Final Choice Validation Summary</td>'
            .'<td style="text-align:right">Page {PAGENO} of {nbpg}</td></tr></table></div>'
        );

        $html = view('reports.pdf.choice-validation-final-summary', [
            'summary' => $summary,
            'statusBreakdown' => $statusBreakdown,
            'examName' => $examName,
            'version' => $version,
            'generatedAt' => now(),
        ])->render();

        $mpdf->WriteHTML($html);

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => "choice-validation-final-summary-v{$version}-".now()->format('Ymd-His').'.pdf',
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
