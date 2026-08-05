<?php
namespace App\Models;
final class VivaFinalizationRun extends ExaminationModel
{
    protected $fillable=['processing_run_id','processing_version','status','summary','finalized_by','finalized_at','notes'];
    protected function casts():array{return ['summary'=>'array','finalized_at'=>'datetime'];}
}
