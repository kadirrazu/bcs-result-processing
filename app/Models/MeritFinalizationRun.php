<?php
namespace App\Models;
final class MeritFinalizationRun extends ExaminationModel
{
    protected $guarded=[];
    protected function casts():array{return['source_snapshot'=>'array','summary'=>'array','finalized_at'=>'datetime'];}
}
