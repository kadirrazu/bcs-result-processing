<?php

namespace App\Reports\Pdf;

use App\Models\AllocationA5Run;
use App\Services\Allocation\AllocationA6SummaryService;
use App\Support\Examinations\ExaminationContext;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class AllocationA6SummaryPdfReport
{
    public function __construct(
        private readonly AllocationA6SummaryService $summary,
        private readonly ExaminationContext $context,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(AllocationA5Run $a5, ?string $examName = null, bool $short = false): array
    {
        $rows = $this->summary->rows($a5);
        if ($rows->isEmpty()) {
            throw new RuntimeException('Allocation Summary has no rows.');
        }

        $totals = $this->summary->totals($rows);
        $generatedAt = now();
        $exam = trim((string) ($examName ?: $this->context->current()?->name ?: 'Selected Examination'));

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $short ? 'A4' : 'A3',
            'orientation' => 'L',
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 22,
            'margin_bottom' => 12,
            'margin_header' => 5,
            'margin_footer' => 5,
            'tempDir' => $this->tempDirectory(),
            'default_font' => 'dejavusans',
        ]);

        $reportTitle = $short ? 'Short Allocation Summary' : 'In-depth Allocation Summary';
        $mpdf->SetTitle($reportTitle);
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLHeader(
            '<div style="text-align:center;font-family:DejaVu Sans,sans-serif;line-height:1.3">'
            .'<div style="font-size:11pt"><strong>'.e($exam).'</strong></div>'
            .'<div style="font-size:10pt"><strong>'.e(strtoupper($reportTitle)).'</strong></div>'
            .'<div style="font-size:8pt;color:#667085">Generated: '.e($generatedAt->format('d M Y, h:i:s A')).'</div>'
            .'</div>'
        );
        $mpdf->SetHTMLFooter(
            '<div style="text-align:right;font-family:DejaVu Sans,sans-serif;font-size:7pt;color:#667085">Page {PAGENO} of {nbpg}</div>'
        );

        $mpdf->WriteHTML(view($short ? 'reports.pdf.allocation-a6-summary-short' : 'reports.pdf.allocation-a6-summary', [
            'rows' => $rows,
            'totals' => $totals,
        ])->render());

        $slug = \Illuminate\Support\Str::slug($exam) ?: 'allocation';
        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => $slug.($short ? '-allocation-short-summary-' : '-allocation-summary-').$generatedAt->format('Ymd-His').'.pdf',
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
