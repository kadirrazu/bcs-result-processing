<?php

namespace App\Reports\Pdf;

use App\Models\CircularEntry;
use App\Reports\Shared\BanglaPdfFontResolver;
use App\Support\Examinations\ExaminationContext;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class CircularAuthorityPreviewPdfReport
{
    public function __construct(
        private readonly BanglaPdfFontResolver $fontResolver,
        private readonly ExaminationContext $examinationContext,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(int $version): array
    {
        $entries = CircularEntry::query()
            ->with(['bachelorSubjects', 'prsSubjects'])
            ->where('version', $version)
            ->orderBy('cadre_type')
            ->orderBy('cadre_serial')
            ->orderByRaw('sub_serial IS NULL DESC')
            ->orderBy('sub_serial')
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            throw new RuntimeException('The selected Circular version has no entries.');
        }

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
            // Use the Bengali-capable report font as the document default as well.
            // This prevents hard-coded/static Bengali labels from silently falling
            // back to a font that does not contain the required glyphs.
            'default_font' => $font['family'],
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $examination = $this->examinationContext->current();
        $examName = trim((string) ($examination?->name ?: ($examination?->bcs_number ? $examination->bcs_number.' BCS Examination' : 'BCS Examination')));
        $reportTitle = 'Circular Authority Preview';

        $mpdf->SetTitle("{$reportTitle} - Version {$version}");
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLHeader(
            '<div style="text-align:center;font-family:'.e($font['family']).';font-size:8.5pt;color:#667085">'
            .e($examName).' | '.e($reportTitle).' | Circular Version: '.e((string) $version)
            .'</div>'
        );
        $mpdf->SetHTMLFooter(
            '<div style="border-top:0.2mm solid #cfd6df;padding-top:1.5mm;font-size:8pt;color:#667085">'
            .'<table width="100%"><tr><td>Authority review copy - not final until confirmed and finalized</td>'
            .'<td style="text-align:right">Page {PAGENO} of {nbpg}</td></tr></table></div>'
        );

        $html = view('reports.pdf.circular-authority-preview', [
            'entries' => $entries,
            'version' => $version,
            'banglaFontFamily' => $font['family'],
            'generatedAt' => now(),
            'examName' => $examName,
            'reportTitle' => $reportTitle,
        ])->render();

        $mpdf->WriteHTML($html);

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => "circular-authority-preview-v{$version}-".now()->format('Ymd-His').'.pdf',
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
