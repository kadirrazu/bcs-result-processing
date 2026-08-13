<?php
namespace App\Services\Merit;
use Illuminate\Support\Collection;
final class MeritRankingService
{
 /** @return array<int,int> tabulation_result_id => rank */
 public function rank(Collection $rows,string $scope):array
 {
  $eligible=$rows->filter(fn($r)=>$this->eligible($r,$scope))->values()->all();
  usort($eligible,fn($a,$b)=>$this->compare($a,$b,$scope));
  $out=[];$rank=0;foreach($eligible as $row){$rank++;$out[(int)$row->id]=$rank;}return $out;
 }
 private function eligible(object $r,string $scope):bool{return match($scope){'general'=>(bool)$r->general_merit_eligible,'technical'=>(bool)$r->technical_merit_eligible,'common'=>(bool)$r->general_merit_eligible||(bool)$r->technical_merit_eligible,default=>false};}
 private function compare(object $a,object $b,string $scope):int
 {
  [$ag,$aw]=$this->applicable($a,$scope);[$bg,$bw]=$this->applicable($b,$scope);
  foreach([[$bg,$ag],[$bw,$aw],[(float)($b->preliminary_mark??-INF),(float)($a->preliminary_mark??-INF)]] as [$left,$right]){if($left!=$right)return $left<=>$right;}
  $ad=(string)($a->birth_date??'9999-12-31');$bd=(string)($b->birth_date??'9999-12-31');if($ad!==$bd)return $ad<=>$bd;
  $ay=$a->graduation_year===null?PHP_INT_MAX:(int)$a->graduation_year;$by=$b->graduation_year===null?PHP_INT_MAX:(int)$b->graduation_year;if($ay!==$by)return $ay<=>$by;
  $areg=(string)$a->reg;$breg=(string)$b->reg;if($areg!==$breg)return $areg<=>$breg;
  return ((int)$a->registration_id)<=>((int)$b->registration_id);
 }
 /** @return array{0:float,1:float} */
 private function applicable(object $r,string $scope):array
 {
  if($scope==='general')return[(float)$r->general_grand_total,(float)$r->general_written_total];
  if($scope==='technical')return[(float)$r->technical_grand_total,(float)$r->technical_written_total];
  $track=strtoupper((string)$r->written_qualified_track);
  if(in_array($track,['GG','GN'],true))return[(float)$r->general_grand_total,(float)$r->general_written_total];
  if(in_array($track,['TT','T'],true))return[(float)$r->technical_grand_total,(float)$r->technical_written_total];
  $general=(bool)$r->general_merit_eligible?(float)$r->general_grand_total:-INF;$technical=(bool)$r->technical_merit_eligible?(float)$r->technical_grand_total:-INF;
  return $general>=$technical?[$general,(float)$r->general_written_total]:[$technical,(float)$r->technical_written_total];
 }
}
