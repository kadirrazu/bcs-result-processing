<?php

namespace App\Reports\Pdf;

use App\Reports\Shared\BanglaPdfFontResolver;
use App\Reports\Themes\ReportTheme;
use App\Reports\Themes\ReportThemeManager;
use App\Services\MasterDataExport\MasterDataExportDefinition;
use App\Services\MasterDataExport\MasterDataExportQuery;
use Illuminate\Support\Carbon;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/** Generate themed, print-ready master-data PDFs with Bengali OTL shaping. */
final class MasterDataPdfReport
{
    public function __construct(
        private readonly MasterDataExportQuery $query,
        private readonly BanglaPdfFontResolver $fontResolver,
        private readonly ReportThemeManager $themeManager,
    ) {}

    /** @return array{content: string, filename: string} */
    public function generate(MasterDataExportDefinition $definition): array
    {
        $generatedAt = now();
        $font = $this->fontResolver->resolve();
        $theme = $this->themeManager->default();
        $tempDirectory = $this->prepareTempDirectory();

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontDirectories = $defaultConfig['fontDir'];

        if (is_string($font['directory']) && $font['directory'] !== '') {
            $fontDirectories[] = $font['directory'];
        }

        $fontDefinition = [
            'R' => $font['regular'],
            'useOTL' => 0x80,
        ];

        if (is_string($font['bold']) && $font['bold'] !== '') {
            $fontDefinition['B'] = $font['bold'];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $definition->orientation() === 'landscape' ? 'L' : 'P',
            'margin_left' => $theme->number('page.margin_left_mm'),
            'margin_right' => $theme->number('page.margin_right_mm'),
            'margin_top' => $theme->number('page.margin_top_mm'),
            'margin_bottom' => $theme->number('page.margin_bottom_mm'),
            'margin_header' => $theme->number('page.margin_header_mm'),
            'margin_footer' => $theme->number('page.margin_footer_mm'),
            'tempDir' => $tempDirectory,
            'fontDir' => array_values(array_unique($fontDirectories)),
            'fontdata' => array_replace($defaultFontConfig['fontdata'], [
                $font['family'] => $fontDefinition,
            ]),
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);

        $mpdf->SetTitle($definition->label());
        $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLHeader($this->headerHtml($definition, $generatedAt, $theme));
        $mpdf->SetHTMLFooter($this->footerHtml($definition, $generatedAt, $theme));

        $html = view('reports.pdf.master-data', [
            'definition' => $definition,
            'records' => $this->query->execute($definition),
            'banglaFontFamily' => $font['family'],
            'theme' => $theme,
        ])->render();

        $mpdf->WriteHTML($html);

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => str($definition->label())->slug().'-'.$generatedAt->format('Ymd-His').'.pdf',
        ];
    }

    private function headerHtml(
        MasterDataExportDefinition $definition,
        Carbon $generatedAt,
        ReportTheme $theme,
    ): string {
        $title = e($definition->label());
        $timestamp = e($generatedAt->format('d M Y, h:i:s A'));
        $family = e($theme->string('fonts.english_family'));
        $text = e($theme->string('colors.text'));
        $muted = e($theme->string('colors.muted'));
        $titleSize = $theme->number('fonts.title_size_pt');
        $metaSize = $theme->number('fonts.meta_size_pt');

        return <<<HTML
        <div style="text-align:center; color:{$text}; font-family:{$family}, sans-serif;">
            <div style="font-size:{$titleSize}pt; font-weight:bold; line-height:1.2;">{$title}</div>
            <div style="margin-top:1.5mm; font-size:{$metaSize}pt; color:{$muted};">Generated at: {$timestamp}</div>
        </div>
        HTML;
    }

    private function footerHtml(
        MasterDataExportDefinition $definition,
        Carbon $generatedAt,
        ReportTheme $theme,
    ): string {
        $label = e($definition->label());
        $timestamp = e($generatedAt->format('d M Y, h:i:s A'));
        $family = e($theme->string('fonts.english_family'));
        $footer = e($theme->string('colors.footer'));
        $border = e($theme->string('colors.footer_border'));
        $size = $theme->number('fonts.footer_size_pt');

        return <<<HTML
        <div style="border-top:0.3mm solid {$border}; padding-top:2mm; color:{$footer}; font-family:{$family}, sans-serif; font-size:{$size}pt;">
            <table width="100%" style="border:0; border-collapse:collapse;"><tr>
                <td width="75%" style="border:0; text-align:left;">{$label} | Generated at {$timestamp}</td>
                <td width="25%" style="border:0; text-align:right;">Page {PAGENO} of {nbpg}</td>
            </tr></table>
        </div>
        HTML;
    }

    private function prepareTempDirectory(): string
    {
        $directory = (string) config('master-data-export.temp_path', storage_path('app/private/mpdf'));

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create mPDF temporary directory [{$directory}].");
        }

        if (! is_writable($directory)) {
            throw new RuntimeException("mPDF temporary directory is not writable [{$directory}].");
        }

        return $directory;
    }
}
