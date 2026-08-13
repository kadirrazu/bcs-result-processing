<?php
namespace App\Models;
final class MeritProcessingRun extends ExaminationModel
{
    protected $guarded=[];
    protected function casts():array{return['progress_percent'=>'float','source_snapshot'=>'array','summary'=>'array','started_at'=>'datetime','finished_at'=>'datetime'];}
}
