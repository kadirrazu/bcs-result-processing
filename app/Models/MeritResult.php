<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class MeritResult extends ExaminationModel
{
    protected $guarded=[];
    protected function casts():array{return['graduation_year'=>'integer','all_merit_tech'=>'array','common_merit_eligible'=>'boolean','general_merit_eligible'=>'boolean','technical_merit_eligible'=>'boolean','processed_at'=>'datetime'];}
    public function run():BelongsTo{return $this->belongsTo(MeritProcessingRun::class,'processing_run_id');}

    public static function allMeritTechJson(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($value)) {
            $value = [];
        }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '{}';
    }
    public function cadreRanks():HasMany{return $this->hasMany(MeritCadreRank::class,'merit_result_id');}
}
