<?php

namespace App\Reports\Pdf;

use App\Models\AllocationSeatBreakupVersion;
use App\Reports\Shared\BanglaPdfFontResolver;
use App\Support\Examinations\ExaminationContext;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class AllocationSeatBreakupPdfReport
{
    public function __construct(
        private readonly BanglaPdfFontResolver $fontResolver,
        private readonly ExaminationContext $examinationContext,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(AllocationSeatBreakupVersion $version): array
    {
        $version = AllocationSeatBreakupVersion::query()->findOrFail($version->id);
        $rows = $this->orderedRows($version);

        if ($rows->isEmpty()) {
            throw new RuntimeException('The selected Seat Breakup version has no rows.');
        }

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
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'margin_header' => 4,
            'margin_footer' => 5,
            'tempDir' => $this->tempDirectory(),
            'fontDir' => array_values(array_unique($fontDirs)),
            'fontdata' => array_replace($fontDefaults['fontdata'], [$font['family'] => $fontData]),
            'default_font' => $font['family'],
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $exam = $this->examinationContext->current();
        $examTitle = trim((string) ($exam?->name ?: ($exam?->bcs_number ? $exam->bcs_number.' BCS Examination' : 'BCS Examination')));
        $status = strtolower((string) $version->status);
        $reportName = match ($status) {
            'finalized' => 'Finalized Allocation Seat Breakup',
            'superseded' => 'Allocation Seat Breakup - Superseded Historical Copy',
            default => 'Allocation Seat Breakup - Pre-Finalization Review',
        };
        $generatedAt = now();

        $mpdf->SetTitle($reportName.' - v'.$version->version);
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLFooter(
            '<div style="border-top:0.2mm solid #cfd6df;padding-top:1.5mm;font-size:7.5pt;color:#667085">'
            .'<table width="100%"><tr><td>Seat Breakup v'.e((string) $version->version).' - '.e(strtoupper((string) $version->status)).'</td>'
            .'<td style="text-align:right">Page {PAGENO} of {nbpg}</td></tr></table></div>'
        );

        $html = view('reports.pdf.allocation-seat-breakup', [
            'version' => $version,
            'rows' => $rows,
            'examTitle' => $examTitle,
            'reportName' => $reportName,
            'generatedAt' => $generatedAt,
            'banglaFontFamily' => $font['family'],
        ])->render();

        $mpdf->WriteHTML($html);

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => 'allocation-seat-breakup-v'.$version->version.'-'.$status.'-'.$generatedAt->format('Ymd-His').'.pdf',
        ];
    }

    private function orderedRows(AllocationSeatBreakupVersion $version)
    {
        return $version->rows()->with('circularEntry')->get()->sort(function ($left, $right): int {
            $a = $left->circularEntry;
            $b = $right->circularEntry;

            $typeA = (string) ($a?->cadre_type?->value ?? $a?->cadre_type ?? '');
            $typeB = (string) ($b?->cadre_type?->value ?? $b?->cadre_type ?? '');
            if ($typeA !== $typeB) return $typeA <=> $typeB;

            $serialA = (int) ($a?->cadre_serial ?? 0);
            $serialB = (int) ($b?->cadre_serial ?? 0);
            if ($serialA !== $serialB) return $serialA <=> $serialB;

            $subA = $a?->sub_serial;
            $subB = $b?->sub_serial;
            if ($subA === null && $subB !== null) return -1;
            if ($subA !== null && $subB === null) return 1;
            if ((int) $subA !== (int) $subB) return (int) $subA <=> (int) $subB;

            return (int) $left->id <=> (int) $right->id;
        })->values();
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
