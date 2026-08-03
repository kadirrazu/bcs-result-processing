<?php
namespace App\Models;
final class VivaBoardImportStaging extends ExaminationModel
{
 protected $table='viva_board_import_staging';
 protected $guarded=[];
 protected function casts():array{return ['raw_payload'=>'array','viva_date'=>'date','mark'=>'decimal:2','viva_cff'=>'boolean','viva_em'=>'boolean','viva_phc'=>'boolean','invalid_flag'=>'boolean','issue_flag'=>'boolean','validation_errors'=>'array','validation_warnings'=>'array'];}
}
