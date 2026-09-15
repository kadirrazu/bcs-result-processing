<?php

namespace App\Reports\Pdf;

use App\Reports\Themes\ReportTheme;
use App\Reports\Themes\ReportThemeManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class ResearchStatisticsPdfReport
{
    public function __construct(private readonly ReportThemeManager $themeManager) {}

    /** @param array<string,mixed> $report @param list<string> $genderNames @return array{content:string,filename:string,identity:string} */
    public function generate(array $report, string $examinationName, array $genderNames): array
    {
        $generatedAt = now(); $identity = Str::upper(Str::random(10)); $theme = $this->themeManager->default();
        $mpdf = new Mpdf(['mode'=>'utf-8','format'=>'A4','orientation'=>'L','margin_left'=>10,'margin_right'=>10,'margin_top'=>34,'margin_bottom'=>15,'margin_header'=>5,'margin_footer'=>6,'tempDir'=>$this->tempDir(),'default_font'=>$theme->string('fonts.english_family')]);
        $mpdf->SetTitle($examinationName.' - '.$report['title']); $mpdf->SetAuthor((string) config('app.name'));
        $mpdf->SetHTMLHeader($this->header($examinationName, (string)$report['title'], $generatedAt, $identity, $theme));
        $mpdf->SetHTMLFooter($this->footer($theme));
        $mpdf->WriteHTML(view('reports.pdf.research-statistics', ['report'=>$report,'genderNames'=>$genderNames,'theme'=>$theme])->render());
        return ['content'=>$mpdf->Output('', Destination::STRING_RETURN),'filename'=>'research-statistics-'.Str::slug((string)$report['key']).'-'.$generatedAt->format('Ymd-His').'.pdf','identity'=>$identity];
    }

    private function header(string $exam, string $title, Carbon $time, string $identity, ReportTheme $theme): string
    {
        $family=e($theme->string('fonts.english_family')); $text=e($theme->string('colors.text')); $muted=e($theme->string('colors.muted'));
        return '<div style="text-align:center;font-family:'.$family.',sans-serif;color:'.$text.';line-height:1.35"><div style="font-size:11pt"><strong>EXAM TITLE:</strong> '.e($exam).'</div><div style="margin-top:1mm;font-size:11pt"><strong>REPORT TITLE:</strong> '.e($title).'</div><div style="margin-top:1mm;font-size:8.5pt;color:'.$muted.'"><strong>REPORT GENERATED ON:</strong> '.e($time->format('d M Y, h:i:s A')).' &nbsp; | &nbsp; <strong>REPORT ID:</strong> '.e($identity).'</div></div>';
    }
    private function footer(ReportTheme $theme): string
    { $family=e($theme->string('fonts.english_family')); $footer=e($theme->string('colors.footer')); return '<div style="text-align:right;font-family:'.$family.',sans-serif;font-size:8pt;color:'.$footer.'">Page {PAGENO} of {nbpg}</div>'; }
    private function tempDir(): string
    { $d=storage_path('app/private/mpdf/research-statistics'); if(!is_dir($d)&&!mkdir($d,0775,true)&&!is_dir($d)) throw new RuntimeException("Unable to create mPDF temporary directory [{$d}]."); if(!is_writable($d)) throw new RuntimeException("mPDF temporary directory is not writable [{$d}]."); return $d; }
}
