<?php
namespace App\Services\Registrations;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
/** Normalize one source row without database access. */
final class RegistrationRowNormalizer
{
 public function normalize(array $row, int $batchId): array
 {
  $ff=$this->nullableInt($row['has_ff_quota']??null);$em=$this->nullableInt($row['has_em_quota']??null);$phc=$this->nullableInt($row['has_phc_quota']??null);
  return ['user_id'=>$this->text($row['user']??null),'reg'=>$this->text($row['reg']??null),'national_id'=>$this->nullableText($row['national_id']??null),
   'name'=>$this->text($row['name']??null),'father_name'=>$this->nullableText($row['fname']??null),'mother_name'=>$this->nullableText($row['mname']??null),
   'name_bn'=>$this->nullableText($row['name_bn']??null),'father_name_bn'=>$this->nullableText($row['fname_bn']??null),'mother_name_bn'=>$this->nullableText($row['mname_bn']??null),
   'birth_date'=>$this->date($row['b_date']??null),'sex_code'=>$this->nullableInt($row['sex']??null),'district_code'=>$this->nullableInt($row['district']??null),'division_code'=>$this->nullableInt($row['division']??null),'university_code'=>$this->nullableInt($row['university']??null),'bachelor_subject_code'=>$this->nullableInt($row['b_subject']??null),'related_subject_code'=>$this->nullableInt($row['rl_subject']??null),
   'cadre_category'=>$this->nullableInt($row['cadre_category']??null),'has_ff_quota'=>$ff,'has_em_quota'=>$em,'has_phc_quota'=>$phc,'has_quota'=>$ff===2||$em!==null||$phc!==null,
   'status'=>$this->nullableText($row['status']??null)??'active','validation_status'=>'valid','comment'=>$this->nullableText($row['comment']??null),'source_batch_id'=>$batchId,'created_at'=>now(),'updated_at'=>now()];
 }
 private function text(mixed $v): string{return trim((string)$v);} private function nullableText(mixed $v): ?string{$v=trim((string)($v??''));return $v===''?null:$v;}
 private function nullableInt(mixed $v): ?int{$v=$this->nullableText($v);return $v===null?null:(is_numeric($v)?(int)$v:null);}
 private function date(mixed $v): ?string{if($v===null||trim((string)$v)==='')return null;if(is_numeric($v))return ExcelDate::excelToDateTimeObject((float)$v)->format('Y-m-d');foreach(['Y-m-d','d/m/Y','d-m-Y','dmY'] as $f){$d=DateTimeImmutable::createFromFormat('!'.$f,trim((string)$v));if($d)return $d->format('Y-m-d');}return null;}
}
