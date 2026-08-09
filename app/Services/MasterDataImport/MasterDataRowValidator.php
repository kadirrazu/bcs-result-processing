<?php
namespace App\Services\MasterDataImport;
use App\Enums\CadreType; use App\Models\CadreMaster; use App\Models\CadreSubMaster; use App\Models\Division; use Illuminate\Support\Facades\Validator; use Illuminate\Validation\Rule;
final class MasterDataRowValidator
{
 public function validate(MasterDataImportDefinition $definition,array $row):array
 {
  $data=collect($row)->map(fn($v)=>is_string($v)?trim($v):$v)->all();$data=$this->normalize($definition,$data);
  $rules=match($definition->key){
   'cadre-masters'=>[
    'cadre_code'=>['required','integer','min:1',function($a,$v,$fail){if(CadreSubMaster::query()->where('sub_cadre_code',(int)$v)->exists())$fail('Code is already used by Sub Cadre Master.');}],
    'cadre_abbr'=>['required','string','max:20'],'cadre_name'=>['required','string','max:255'],'cadre_name_bn'=>['required','string','max:255'],
    'post_name'=>['nullable','string','max:255'],'post_name_bn'=>['nullable','string','max:255'],'cadre_type'=>['required',Rule::enum(CadreType::class)],'display_order'=>['required','integer','min:0'],'is_active'=>['required','boolean'],
   ],
   'cadre-sub-masters'=>[
    'parent_cadre_code'=>['required','integer','min:1',Rule::exists('cadre_masters','cadre_code')->where('is_active',true)],
    'sub_cadre_code'=>['required','integer','min:1',function($a,$v,$fail){if(CadreMaster::query()->where('cadre_code',(int)$v)->exists())$fail('Code is already used by Cadre Master.');}],
    'sub_cadre_abbr'=>['nullable','string','max:20'],'post_name'=>['required','string','max:255'],'post_name_bn'=>['required','string','max:255'],'display_order'=>['required','integer','min:0'],'is_active'=>['required','boolean'],
   ],
   'divisions'=>['code'=>['required','integer','min:1'],'name'=>['required','string','max:120'],'name_bn'=>['nullable','string','max:150'],'is_active'=>['required','boolean']],
   'districts'=>['code'=>['required','integer','min:1'],'division_code'=>['required','integer','min:1',Rule::exists((new Division)->getTable(),'code')->where('is_active',true)],'name'=>['required','string','max:120'],'name_bn'=>['nullable','string','max:150'],'is_active'=>['required','boolean']],
   'universities'=>['code'=>['required','integer','min:1'],'name'=>['required','string','max:255'],'name_bn'=>['nullable','string','max:255'],'is_active'=>['required','boolean']],
   default=>['subject_code'=>['required','string','max:30'],'subject_name'=>['required','string','max:255'],'is_active'=>['required','boolean']],
  };
  $v=Validator::make($data,$rules);return ['valid'=>!$v->fails(),'data'=>$data,'errors'=>$v->errors()->all()];
 }
 private function normalize(MasterDataImportDefinition $d,array $data):array
 {
  $data['is_active']=$this->boolean($data['is_active']??true);
  if($d->key==='cadre-masters'){
   $data['cadre_abbr']=strtoupper((string)($data['cadre_abbr']??''));$data['cadre_type']=strtoupper((string)($data['cadre_type']??''));$data['display_order']=(int)($data['display_order']??0);$data['cadre_code']=is_numeric($data['cadre_code']??null)?(int)$data['cadre_code']:$data['cadre_code'];$data['post_name']=$this->nullableText($data['post_name']??null);$data['post_name_bn']=$this->nullableText($data['post_name_bn']??null);
  }elseif($d->key==='cadre-sub-masters'){
   $data['parent_cadre_code']=is_numeric($data['parent_cadre_code']??null)?(int)$data['parent_cadre_code']:$data['parent_cadre_code'];$data['sub_cadre_code']=is_numeric($data['sub_cadre_code']??null)?(int)$data['sub_cadre_code']:$data['sub_cadre_code'];$abbr=strtoupper(trim((string)($data['sub_cadre_abbr']??'')));$data['sub_cadre_abbr']=$abbr===''?null:$abbr;$data['display_order']=(int)($data['display_order']??0);
  }elseif(in_array($d->key,['divisions','districts','universities'],true)){$data['code']=is_numeric($data['code']??null)?(int)$data['code']:$data['code'];$data['name_bn']=$this->nullableText($data['name_bn']??null);if($d->key==='districts')$data['division_code']=is_numeric($data['division_code']??null)?(int)$data['division_code']:$data['division_code'];}
  else{$data['subject_code']=strtoupper((string)($data['subject_code']??''));}
  return $data;
 }
 private function nullableText(mixed $v):?string{$v=trim((string)($v??''));return $v===''?null:$v;}
 private function boolean(mixed $v):bool{return in_array(strtolower(trim((string)$v)),['1','true','yes','active'],true);}
}
