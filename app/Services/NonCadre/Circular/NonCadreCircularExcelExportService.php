<?php
namespace App\Services\NonCadre\Circular;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
final class NonCadreCircularExcelExportService
{
 public function __construct(private readonly NonCadreCircularDatasetService $dataset) {}
 public function generate(int $id): array
 {
  $v=$this->dataset->version($id); $groups=$this->dataset->posts($id)->groupBy(fn($p)=>$p->post_grade===null?'Ungraded':(string)$p->post_grade);
  $book=new Spreadsheet; $s=$book->getActiveSheet(); $s->setTitle('Finalized Circular'); $r=1;
  $headers=['Grade','Serial','Sub Serial','Ministry','Ministry BN','Entity','Entity BN','Post Title','Post Title BN','Post Code','Post Count','Bachelor Subjects','Status','Special Requirement','Special Requirement Note'];
  foreach($groups as $grade=>$posts){
   $s->mergeCells("A{$r}:O{$r}"); $s->setCellValue("A{$r}",'Grade: '.$grade); $s->getStyle("A{$r}:O{$r}")->getFont()->setBold(true); $r++;
   foreach($headers as $i=>$h)$s->setCellValue([$i+1,$r],$h); $s->getStyle("A{$r}:O{$r}")->getFont()->setBold(true); $s->getStyle("A{$r}:O{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); $r++;
   foreach($posts as $p){$vals=[$p->post_grade,$p->post_serial,$p->post_sub_serial,$p->ministry,$p->ministry_bn,$p->entity,$p->entity_bn,$p->post_title,$p->post_title_bn,$p->post_code,$p->post_count,$p->bachelor_subject_codes?str_replace('|',', ',$p->bachelor_subject_codes):'ALL',$p->status,$p->special_requirement?'YES':'NO',$p->special_requirement_note]; foreach($vals as $i=>$val){if($i===9)$s->setCellValueExplicit([$i+1,$r],(string)$val,DataType::TYPE_STRING);else $s->setCellValue([$i+1,$r],$val);} $r++;}
   $s->mergeCells("A{$r}:J{$r}"); $s->setCellValue("A{$r}",'TOTAL POST COUNT'); $s->setCellValue("K{$r}",(int)$posts->sum('post_count')); $s->getStyle("A{$r}:O{$r}")->getFont()->setBold(true); $r+=2;
  }
  foreach(range('A','O') as $c)$s->getColumnDimension($c)->setAutoSize(true);
  $name='non-cadre-circular-v'.$v->version.'-'.now()->format('Ymd-His').'.xlsx'; $path=storage_path('app/private/exports/'.$name); if(!is_dir(dirname($path)))mkdir(dirname($path),0775,true); (new Xlsx($book))->save($path); $content=file_get_contents($path); @unlink($path); $book->disconnectWorksheets(); return ['content'=>$content,'filename'=>$name];
 }
}