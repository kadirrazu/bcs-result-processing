<?php
namespace App\Models;
final class TabulationProcessingState extends ExaminationModel
{
    public $incrementing = false;
    protected $fillable=['id','status','latest_run_id','latest_finalization_run_id','is_stale','stale_reason','source_snapshot','summary','finalized_by','finalized_at'];
    protected function casts():array{return['is_stale'=>'boolean','source_snapshot'=>'array','summary'=>'array','finalized_at'=>'datetime'];}
}
