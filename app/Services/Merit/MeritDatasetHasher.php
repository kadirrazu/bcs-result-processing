<?php
namespace App\Services\Merit;
use App\Models\MeritCadreRank;use App\Models\MeritResult;
final class MeritDatasetHasher
{
 public function hash(int $runId):string
 {
  $ctx=hash_init('sha256');
  MeritResult::query()->where('processing_run_id',$runId)->orderBy('id')->chunkById(500,function($rows)use($ctx){foreach($rows as $r){$payload=['registration_id'=>(int)$r->registration_id,'reg'=>(string)$r->reg,'common'=>$r->common_merit_position,'general'=>$r->general_merit_position,'technical'=>$r->technical_merit_position,'all_merit_tech'=>$this->canonicalize((array)$r->all_merit_tech),'status_reason'=>$r->status_reason];hash_update($ctx,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");}});
  MeritCadreRank::query()->where('processing_run_id',$runId)->orderBy('cadre_code')->orderBy('cadre_merit_position')->orderBy('id')->chunkById(500,function($rows)use($ctx){foreach($rows as $r){$payload=['registration_id'=>(int)$r->registration_id,'cadre_code'=>(int)$r->cadre_code,'cadre_abbr'=>(string)$r->cadre_abbr,'cadre_type'=>(string)$r->cadre_type,'cadre_merit_position'=>(int)$r->cadre_merit_position,'source_merit_position'=>(int)$r->source_merit_position,'choice_position'=>(int)$r->choice_position];hash_update($ctx,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");}});
  return hash_final($ctx);
 }
 private function canonicalize(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map(fn($x)=>$this->canonicalize($x),$v);ksort($v);foreach($v as $k=>$x)$v[$k]=$this->canonicalize($x);return $v;}
}
