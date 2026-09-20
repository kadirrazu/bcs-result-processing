<?php

namespace App\Reports\Pdf\NonCadre;

use App\Reports\Shared\BanglaPdfFontResolver;
use App\Services\NonCadre\SeatBreakup\NonCadreSeatBreakupService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Support\Facades\DB;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class NonCadreSeatBreakupPdfReport
{
    public function __construct(
        private readonly BanglaPdfFontResolver $fontResolver,
        private readonly NonCadreSeatBreakupService $service,
        private readonly ExaminationContext $context,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(int $versionId): array
    {
        $version = DB::connection('exam')->table('non_cadre_seat_breakup_versions')->find($versionId);
        if (! $version) {
            abort(404);
        }

        $rows = $this->service->orderedRows($versionId);
        if ($rows->isEmpty()) {
            throw new RuntimeException('The selected Non-Cadre Seat Breakup version has no rows.');
        }

        $groups = $rows->groupBy(fn ($row) => $row->post_grade === null ? 'Ungraded' : (string) $row->post_grade);
        $totals = $this->service->totals($versionId);
        $circular = DB::connection('exam')->table('non_cadre_circular_versions')->find($version->circular_version_id);

        $font = $this->fontResolver->resolve();
        $defaults = (new ConfigVariables)->getDefaults();
        $fontDefaults = (new FontVariables)->getDefaults();
        $fontDirs = $defaults['fontDir'];
        if (! empty($font['directory'])) {
            $fontDirs[] = $font['directory'];
        }
        $fontData = ['R' => $font['regular'], 'useOTL' => 0x80];
        if (! empty($font['bold'])) {
            $fontData['B'] = $font['bold'];
        }

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 7,
            'margin_right' => 7,
            'margin_top' => 24,
            'margin_bottom' => 14,
            'margin_header' => 5,
            'margin_footer' => 5,
            'tempDir' => $this->tempDirectory(),
            'fontDir' => array_values(array_unique($fontDirs)),
            'fontdata' => array_replace($fontDefaults['fontdata'], [$font['family'] => $fontData]),
            'default_font' => $font['family'],
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);

        $exam = $this->context->current();
        $examName = trim((string) ($exam?->name ?: ($exam?->bcs_number ? $exam->bcs_number.' BCS Examination' : 'BCS Examination')));
        $generatedAt = now();
        $reportTitle = 'Non-Cadre Seat Breakup';

        $pdf->SetTitle($reportTitle.' - v'.$version->version);
        $pdf->SetAuthor((string) config('app.name'));
        $pdf->SetHTMLHeader(
            '<div style="text-align:center;line-height:1.35;color:#101828">'
            .'<div style="font-size:11pt"><strong>'.e($examName).'</strong></div>'
            .'<div style="font-size:11pt"><strong>'.e($reportTitle).'</strong></div>'
            .'<div style="font-size:8.5pt;color:#667085">Circular Version: '.e((string) ($circular->version ?? '—'))
            .' &nbsp; | &nbsp; Seat Breakup Version: '.e((string) $version->version)
            .' &nbsp; | &nbsp; Generated: '.e($generatedAt->format('d M Y, h:i:s A')).'</div></div>'
        );
        $pdf->SetHTMLFooter('<div style="font-size:8pt;color:#667085;border-top:.2mm solid #bbb;padding-top:1mm">Non-Cadre Seat Breakup <span style="float:right">Page {PAGENO} of {nbpg}</span></div>');

        $pdf->WriteHTML(view('reports.pdf.non-cadre.seat-breakup', [
            'version' => $version,
            'circular' => $circular,
            'groups' => $groups,
            'totals' => $totals,
            'fontFamily' => $font['family'],
        ])->render());

        return [
            'content' => $pdf->Output('', Destination::STRING_RETURN),
            'filename' => 'non-cadre-seat-breakup-v'.$version->version.'-'.$generatedAt->format('Ymd-His').'.pdf',
        ];
    }

    private function tempDirectory(): string
    {
        $path = storage_path('app/private/mpdf');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create mPDF temp directory.');
        }
        return $path;
    }
}
