<?php
namespace App\Models;
final class TabulationProcessingRun extends ExaminationModel
{
    protected $fillable=['processing_version','status','total_rows','processed_rows','valid_rows','warning_rows','error_rows','general_pass_count','technical_pass_count','general_merit_eligible_count','technical_merit_eligible_count','progress_percent','current_step','source_snapshot','rule_snapshot','summary','failure_message','created_by','started_at','finished_at'];
    protected function casts():array{return['progress_percent'=>'float','source_snapshot'=>'array','rule_snapshot'=>'array','summary'=>'array','started_at'=>'datetime','finished_at'=>'datetime'];}
}
