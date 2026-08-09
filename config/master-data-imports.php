<?php
use App\Models\BachelorSubject; use App\Models\CadreMaster; use App\Models\CadreSubMaster; use App\Models\PostRelatedSubject;
return [
 'cadre-masters'=>[
  'label'=>'Cadre Master','model'=>CadreMaster::class,'route'=>'cadre-masters.index','unique_by'=>'cadre_code','additional_unique_by'=>['cadre_abbr'],
  'headers'=>['cadre_code','cadre_abbr','cadre_name','cadre_name_bn','post_name','post_name_bn','cadre_type','display_order','is_active'],
  'required'=>['cadre_code','cadre_abbr','cadre_name','cadre_name_bn','cadre_type'],
  'sample'=>[110,'ADMN','BCS (Administration)','বিসিএস (প্রশাসন)','Assistant Commissioner','সহকারী কমিশনার','GG',10,1],
 ],
 'cadre-sub-masters'=>[
  'label'=>'Sub Cadre Master','model'=>CadreSubMaster::class,'route'=>'cadre-sub-masters.index','unique_by'=>'sub_cadre_code','additional_unique_by'=>[],
  'headers'=>['parent_cadre_code','sub_cadre_code','sub_cadre_abbr','post_name','post_name_bn','display_order','is_active'],
  'required'=>['parent_cadre_code','sub_cadre_code','post_name','post_name_bn'],
  'sample'=>[610,611,'','Lecturer (Bangla)','প্রভাষক (বাংলা)',1,1],
 ],
 'bachelor-subjects'=>['label'=>'Bachelor Subjects','model'=>BachelorSubject::class,'route'=>'bachelor-subjects.index','unique_by'=>'subject_code','additional_unique_by'=>[],'headers'=>['subject_code','subject_name','is_active'],'required'=>['subject_code','subject_name'],'sample'=>['001','Bangla',1]],
 'post-related-subjects'=>['label'=>'Post Related Subjects','model'=>PostRelatedSubject::class,'route'=>'post-related-subjects.index','unique_by'=>'subject_code','additional_unique_by'=>[],'headers'=>['subject_code','subject_name','is_active'],'required'=>['subject_code','subject_name'],'sample'=>['MEDI','Medical Science',1]],
 'divisions'=>['label'=>'Divisions','model'=>\App\Models\Division::class,'route'=>'registration-masters.index','route_parameters'=>['type'=>'divisions'],'unique_by'=>'code','additional_unique_by'=>[],'headers'=>['code','name','name_bn','is_active'],'required'=>['code','name'],'sample'=>[1,'Dhaka','ঢাকা',1]],
 'districts'=>['label'=>'Districts','model'=>\App\Models\District::class,'route'=>'registration-masters.index','route_parameters'=>['type'=>'districts'],'unique_by'=>'code','additional_unique_by'=>[],'headers'=>['code','division_code','name','name_bn','is_active'],'required'=>['code','division_code','name'],'sample'=>[1,1,'Dhaka','ঢাকা',1]],
 'universities'=>['label'=>'Universities','model'=>\App\Models\University::class,'route'=>'registration-masters.index','route_parameters'=>['type'=>'universities'],'unique_by'=>'code','additional_unique_by'=>[],'headers'=>['code','name','name_bn','is_active'],'required'=>['code','name'],'sample'=>[1,'University of Dhaka','ঢাকা বিশ্ববিদ্যালয়',1]],
];
