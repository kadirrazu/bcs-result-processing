<?php

namespace App\Reports\Pdf;

use Illuminate\Support\Str;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class AllocationVerificationPdfReport
{
    /** @param array{title:string,cadre:?array,rows:\Illuminate\Support\Collection,summary:?array} $data */
    public function generate(array $data, string $examinationName, string $reportType, ?int $cadreCode = null): array
    {
        $generatedAt = now();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'Legal',
            'orientation' => 'L',
            'margin_left' => 12.7,
            'margin_right' => 12.7,
            'margin_top' => 12.7,
            'margin_bottom' => 12.7,
            'margin_header' => 5,
            'margin_footer' => 5,
            'tempDir' => $this->tempDirectory(),
            'default_font' => 'dejavusans',
        ]);

        $title = (string) ($data['title'] ?? 'Allocation Verification Report');
        $mpdf->SetTitle($examinationName.' - '.$title);
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLFooter(
            '<div style="text-align:right;font-family:DejaVu Sans,sans-serif;font-size:7pt;color:#667085">'
            .'Page {PAGENO} of {nbpg}</div>'
        );

        $mpdf->WriteHTML(view('reports.pdf.allocation-verification', $data + [
            'examinationName' => $examinationName,
            'generatedAt' => $generatedAt,
        ])->render());

        $scope = in_array($reportType, ['general-cadre', 'technical-cadre'], true) && $cadreCode
            ? $reportType.'-'.$cadreCode
            : $reportType;

        $slug = Str::slug($examinationName) ?: 'bcs';

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => $slug.'-allocation-verification-'.$scope.'-'.$generatedAt->format('Ymd-His').'.pdf',
        ];
    }

    /**
     * @param array{title:string,rows_per_group:int,sections:\Illuminate\Support\Collection} $data
     */
    public function generateCadreSerialMerit(array $data, string $examinationName): array
    {
        $generatedAt = now();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 12.7,
            'margin_right' => 12.7,
            'margin_top' => 12.7,
            'margin_bottom' => 12.7,
            'margin_header' => 5,
            'margin_footer' => 5,
            'tempDir' => $this->tempDirectory(),
            'default_font' => 'dejavusans',
        ]);

        $title = (string) ($data['title'] ?? 'Cadre-wise Serial, Merit & Allocation Basis Verification Report');
        $mpdf->SetTitle($examinationName.' - '.$title);
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLFooter(
            '<div style="text-align:right;font-family:DejaVu Sans,sans-serif;font-size:7pt;color:#667085">'
            .'Page {PAGENO} of {nbpg}</div>'
        );

        // IMPORTANT: Never render the full multi-cadre report into one giant HTML string.
        // mPDF applies PCRE parsing internally and large all-at-once HTML can exceed
        // pcre.backtrack_limit. Load CSS once, then render/write one A4 page at a time.
        $mpdf->WriteHTML(
            view('reports.pdf.cadre-serial-merit-verification-style')->render(),
            HTMLParserMode::HEADER_CSS
        );

        $pageNumber = 0;

        foreach ($data['sections'] as $section) {
            foreach ($section['pages'] as $pageIndex => $page) {
                if ($pageNumber > 0) {
                    $mpdf->AddPage('L');
                }

                $groups = $page['groups'];
                $maxRows = collect($groups)
                    ->map(fn ($group) => $group->count())
                    ->max() ?? 0;

                $pageHtml = view('reports.pdf.cadre-serial-merit-verification-page', [
                    'examinationName' => $examinationName,
                    'title' => $title,
                    'section' => $section,
                    'pageIndex' => $pageIndex,
                    'groups' => $groups,
                    'maxRows' => $maxRows,
                    'generatedAt' => $generatedAt,
                ])->render();

                $mpdf->WriteHTML($pageHtml, HTMLParserMode::HTML_BODY);
                $pageNumber++;
            }
        }

        // The report normally has at least one ACTIVE allocation section, but keep
        // the export valid even if the publication population is unexpectedly empty.
        if ($pageNumber === 0) {
            $mpdf->WriteHTML(
                '<div class="empty">No ACTIVE allocated candidate was found for the current report authority.</div>',
                HTMLParserMode::HTML_BODY
            );
        }

        $slug = Str::slug($examinationName) ?: 'bcs';

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => $slug.'-cadre-wise-serial-merit-basis-verification-'.$generatedAt->format('Ymd-His').'.pdf',
        ];
    }

    private function tempDirectory(): string
    {
        $path = storage_path('app/private/mpdf/allocation-verification');

        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create mPDF temporary directory [{$path}].");
        }

        if (! is_writable($path)) {
            throw new RuntimeException("mPDF temporary directory is not writable [{$path}].");
        }

        return $path;
    }
}
