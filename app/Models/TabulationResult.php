<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class TabulationResult extends ExaminationModel
{
    protected $fillable=['processing_run_id','processing_version','registration_id','preliminary_result_id','written_result_id','viva_result_id','user_id','reg','written_qualified_track','preliminary_mark','general_written_total','technical_written_total','viva_mark','general_grand_total','technical_grand_total','general_pf','technical_pf','general_merit_eligible','technical_merit_eligible','validation_status','validation_errors','review_warnings','source_snapshot','processing_flags','processed_at'];
    protected function casts():array{return['preliminary_mark'=>'decimal:2','general_written_total'=>'decimal:2','technical_written_total'=>'decimal:2','viva_mark'=>'decimal:2','general_grand_total'=>'decimal:2','technical_grand_total'=>'decimal:2','general_merit_eligible'=>'boolean','technical_merit_eligible'=>'boolean','validation_errors'=>'array','review_warnings'=>'array','source_snapshot'=>'array','processing_flags'=>'array','processed_at'=>'datetime'];}
    public function run():BelongsTo{return $this->belongsTo(TabulationProcessingRun::class,'processing_run_id');}

    public static function grandTotalDisplayFor(?string $track,string $side,mixed $value):string
    {
        $track=strtoupper(trim((string)$track));
        if($side==='general'){
            if($track==='T')return 'TRACK FAILED';
            if($track==='TT')return 'NOT APPLICABLE';
            return $value===null?'—':number_format((float)$value,2,'.','');
        }
        if($track==='GN')return 'TRACK FAILED';
        if($track==='GG')return 'NOT APPLICABLE';
        return $value===null?'—':number_format((float)$value,2,'.','');
    }

    public function generalGrandTotalDisplay():string{return self::grandTotalDisplayFor($this->written_qualified_track,'general',$this->general_grand_total);}
    public function technicalGrandTotalDisplay():string{return self::grandTotalDisplayFor($this->written_qualified_track,'technical',$this->technical_grand_total);}
}
