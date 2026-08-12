<?php

namespace App\Reports\Pdf;

use App\Models\PreliminaryResult;
use App\Models\Registration;
use App\Models\TabulationResult;
use App\Models\VivaResult;
use App\Models\WrittenResult;
use App\Reports\Shared\BanglaPdfFontResolver;
use App\Services\Tabulation\TabulationSourceDerivedVerificationService;
use App\Support\Examinations\ExaminationContext;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class TabulationIndividualPdfReport
{
    public function __construct(
        private readonly BanglaPdfFontResolver $fontResolver,
        private readonly ExaminationContext $context,
        private readonly TabulationSourceDerivedVerificationService $verificationService,
    ) {}

    public function generate(TabulationResult $result): array
    {
        $registration = Registration::query()->findOrFail($result->registration_id);
        $preliminary = $result->preliminary_result_id ? PreliminaryResult::query()->find($result->preliminary_result_id) : null;
        $written = WrittenResult::query()->findOrFail($result->written_result_id);
        $viva = VivaResult::query()->findOrFail($result->viva_result_id);
        $verificationRows = $this->verificationService->build($result, $preliminary, $written, $viva);

        $font = $this->fontResolver->resolve();
        $defaults = (new ConfigVariables)->getDefaults();
        $fontDefaults = (new FontVariables)->getDefaults();
        $dirs = $defaults['fontDir'];
        if ($font['directory']) {
            $dirs[] = $font['directory'];
        }

        $fontData = ['R' => $font['regular'], 'useOTL' => 0x80];
        if ($font['bold']) {
            $fontData['B'] = $font['bold'];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 14,
            'tempDir' => $this->tempDirectory(),
            'fontDir' => array_values(array_unique($dirs)),
            'fontdata' => array_replace($fontDefaults['fontdata'], [$font['family'] => $fontData]),
            'default_font' => $font['family'],
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);

        $mpdf->SetTitle('Individual Finalized Tabulation - '.$result->reg);
        $mpdf->SetHTMLFooter('<div style="border-top:0.2mm solid #ccc;font-size:8pt;padding-top:1.5mm">Individual Finalized Tabulation <span style="float:right">Page {PAGENO} of {nbpg}</span></div>');

        $html = view('reports.pdf.tabulation-individual', [
            'result' => $result,
            'registration' => $registration,
            'preliminary' => $preliminary,
            'written' => $written,
            'viva' => $viva,
            'verificationRows' => $verificationRows,
            'exam' => $this->context->current(),
            'generatedAt' => now(),
        ])->render();

        $mpdf->WriteHTML($html);

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => 'tabulation-'.$result->reg.'-v'.$result->processing_version.'.pdf',
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
