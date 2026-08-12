<?php
namespace App\Models;
final class TabulationProcessingAudit extends ExaminationModel
{
    public $timestamps=false;
    protected $fillable=['event','processing_run_id','actor_id','from_status','to_status','reason','summary','created_at'];
    protected function casts():array{return['summary'=>'array','created_at'=>'datetime'];}
}
