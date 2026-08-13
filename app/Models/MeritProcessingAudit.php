<?php
namespace App\Models;
final class MeritProcessingAudit extends ExaminationModel
{
    public $timestamps=false;
    protected $guarded=[];
    protected function casts():array{return['summary'=>'array','created_at'=>'datetime'];}
}
