<?php
namespace App\Models;
final class TabulationFinalizationRun extends ExaminationModel
{
    protected $fillable=['processing_run_id','processing_version','status','source_snapshot','dataset_hash','summary','finalized_by','finalized_at','notes'];
    protected function casts():array{return['source_snapshot'=>'array','summary'=>'array','finalized_at'=>'datetime'];}
}
