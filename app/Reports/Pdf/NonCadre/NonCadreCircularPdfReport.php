<?php
namespace App\Reports\Pdf\NonCadre;
use App\Reports\Shared\BanglaPdfFontResolver;
use App\Services\NonCadre\Circular\NonCadreCircularDatasetService;
use App\Support\Examinations\ExaminationContext;
use Mpdf\Config\ConfigVariables; use Mpdf\Config\FontVariables; use Mpdf\Mpdf; use Mpdf\Output\Destination; use RuntimeException;
final class NonCadreCircularPdfReport
{
 public function __construct(private readonly BanglaPdfFontResolver $fontResolver,private readonly NonCadreCircularDatasetService $dataset,private readonly ExaminationContext $context){}
 public function generate(int $id): array
 {
  $v=$this->dataset->version($id); $groups=$this->dataset->posts($id)->groupBy(fn($p)=>$p->post_grade===null?'Ungraded':(string)$p->post_grade); $font=$this->fontResolver->resolve(); $d=(new ConfigVariables)->getDefaults(); $fd=(new FontVariables)->getDefaults(); $dirs=$d['fontDir']; if(!empty($font['directory']))$dirs[]=$font['directory']; $fontData=['R'=>$font['regular'],'useOTL'=>0x80]; if(!empty($font['bold']))$fontData['B']=$font['bold'];
  $pdf=new Mpdf(['mode'=>'utf-8','format'=>'A4-L','margin_left'=>6,'margin_right'=>6,'margin_top'=>12,'margin_bottom'=>12,'tempDir'=>$this->tmp(),'fontDir'=>array_values(array_unique($dirs)),'fontdata'=>array_replace($fd['fontdata'],[$font['family']=>$fontData]),'default_font'=>$font['family'],'autoScriptToLang'=>true,'autoLangToFont'=>false]);
  $exam=$this->context->current(); $examName=$exam?->name ?: (($exam?->bcs_number ? $exam->bcs_number.' BCS Examination':null) ?: 'BCS Examination'); $pdf->SetTitle('Finalized Non-Cadre Circular'); $pdf->SetHTMLFooter('<div style="font-size:8pt;border-top:.2mm solid #bbb">Finalized Non-Cadre Circular <span style="float:right">Page {PAGENO} of {nbpg}</span></div>');
  $pdf->WriteHTML(view('reports.pdf.non-cadre.circular',['version'=>$v,'groups'=>$groups,'examName'=>$examName,'generatedAt'=>now(),'fontFamily'=>$font['family']])->render()); return ['content'=>$pdf->Output('',Destination::STRING_RETURN),'filename'=>'non-cadre-circular-v'.$v->version.'-'.now()->format('Ymd-His').'.pdf'];
 }
 private function tmp():string{$p=storage_path('app/private/mpdf');if(!is_dir($p)&&!mkdir($p,0775,true)&&!is_dir($p))throw new RuntimeException('Unable to create mPDF temp directory.');return $p;}
}