<?php
namespace App\Models;
use App\Enums\CircularProcessingStatus;
final class CircularProcessingState extends ExaminationModel
{
 public $incrementing=false;
 protected $fillable=['id','status','current_version','approved_version','confirmed_version','finalized_version','approved_by','approved_at','confirmed_by','confirmed_at','confirmation_notes','finalized_by','finalized_at','is_stale','stale_reason','summary'];
 protected function casts():array{return ['status'=>CircularProcessingStatus::class,'current_version'=>'integer','approved_version'=>'integer','confirmed_version'=>'integer','finalized_version'=>'integer','approved_at'=>'datetime','confirmed_at'=>'datetime','finalized_at'=>'datetime','is_stale'=>'boolean','summary'=>'array'];}
}
