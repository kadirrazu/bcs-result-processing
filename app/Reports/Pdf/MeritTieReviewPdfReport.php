<?php
namespace App\Reports\Pdf;
use App\Models\MeritProcessingRun;use App\Reports\Shared\BanglaPdfFontResolver;use App\Services\Merit\MeritTieReviewService;use App\Support\Examinations\ExaminationContext;use Mpdf\Config\ConfigVariables;use Mpdf\Config\FontVariables;use Mpdf\Mpdf;use Mpdf\Output\Destination;use RuntimeException;
final class MeritTieReviewPdfReport
{
 public function __construct(private readonly BanglaPdfFontResolver $fontResolver,private readonly ExaminationContext $context,private readonly MeritTieReviewService $ties){}
 public function generate(MeritProcessingRun $run):array
 {
  $font=$this->fontResolver->resolve();$defaults=(new ConfigVariables)->getDefaults();$fontDefaults=(new FontVariables)->getDefaults();$dirs=$defaults['fontDir'];if($font['directory'])$dirs[]=$font['directory'];$fontData=['R'=>$font['regular'],'useOTL'=>0x80];if($font['bold'])$fontData['B']=$font['bold'];
  $mpdf=new Mpdf(['mode'=>'utf-8','format'=>'A4-L','margin_left'=>10,'margin_right'=>10,'margin_top'=>10,'margin_bottom'=>13,'tempDir'=>$this->tempDirectory(),'fontDir'=>array_values(array_unique($dirs)),'fontdata'=>array_replace($fontDefaults['fontdata'],[$font['family']=>$fontData]),'default_font'=>$font['family'],'autoScriptToLang'=>true,'autoLangToFont'=>false]);
  $mpdf->SetTitle('Merit Tie Review');$mpdf->SetHTMLFooter('<div style="border-top:0.2mm solid #ccc;font-size:8pt;padding-top:1.5mm">Merit Tie Review <span style="float:right">Page {PAGENO} of {nbpg}</span></div>');
  $data=$this->ties->forRun($run);$mpdf->WriteHTML(view('reports.pdf.merit-tie-review',['run'=>$run,'data'=>$data,'exam'=>$this->context->current(),'generatedAt'=>now()])->render());
  return ['content'=>$mpdf->Output('',Destination::STRING_RETURN),'filename'=>'merit-tie-review-v'.$run->processing_version.'-'.now()->format('Ymd-His').'.pdf'];
 }
 private function tempDirectory():string{$path=storage_path('app/private/mpdf');if(!is_dir($path)&&!mkdir($path,0775,true)&&!is_dir($path))throw new RuntimeException('Unable to create mPDF temp directory.');return $path;}
}
