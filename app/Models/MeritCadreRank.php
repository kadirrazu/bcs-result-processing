<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class MeritCadreRank extends ExaminationModel
{
    public $timestamps=false;
    protected $guarded=[];
    protected function casts():array{return['cadre_code'=>'integer','cadre_merit_position'=>'integer','source_merit_position'=>'integer','choice_position'=>'integer','created_at'=>'datetime'];}
    public function result():BelongsTo{return $this->belongsTo(MeritResult::class,'merit_result_id');}
}
