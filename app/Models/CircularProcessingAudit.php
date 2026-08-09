<?php
namespace App\Models;
final class CircularProcessingAudit extends ExaminationModel
{
 public $timestamps=false;
 protected $fillable=['action','actor_id','actor_name','reason','changed_fields','before_snapshot','after_snapshot','summary','created_at'];
 protected function casts():array{return ['changed_fields'=>'array','before_snapshot'=>'array','after_snapshot'=>'array','summary'=>'array','created_at'=>'datetime'];}
}
