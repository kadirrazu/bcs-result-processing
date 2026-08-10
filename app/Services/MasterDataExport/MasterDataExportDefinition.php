<?php
namespace App\Services\MasterDataExport;
use App\Services\MasterDataImport\MasterDataImportDefinition;
final readonly class MasterDataExportDefinition
{
 public function __construct(public MasterDataImportDefinition $import){}
 public static function resolve(string $key):self{return new self(MasterDataImportDefinition::resolve($key));}
 public function key():string{return $this->import->key;} public function label():string{return $this->import->label();} public function model():string{return $this->import->model();} public function headers():array{return $this->import->headers();}
 public function orientation():string{return in_array($this->key(),['cadre-masters','cadre-sub-masters'],true)?'landscape':'portrait';}
 public function columns():array{return match($this->key()){
  'cadre-masters'=>['cadre_code'=>'Code','cadre_abbr'=>'Abbreviation','cadre_name'=>'Cadre Name','cadre_name_bn'=>'Cadre Name (BN)','post_name'=>'Post Name','post_name_bn'=>'Post Name (BN)','cadre_type'=>'Type','display_order'=>'Display Order','is_active'=>'Status'],
  'cadre-sub-masters'=>['parent_cadre_code'=>'Parent Code','sub_cadre_code'=>'Sub Code','sub_cadre_abbr'=>'Abbreviation','post_name'=>'Post Name','post_name_bn'=>'Post Name (BN)','display_order'=>'Display Order','is_active'=>'Status'],
  default=>['subject_code'=>'Code','subject_name'=>'Name','is_active'=>'Status'],
 };}
}
