<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class ChoiceValidationResult extends ExaminationModel {
    protected $guarded=[];
    protected function casts():array{return ['validated_choice_codes'=>'array','eligibility_snapshot'=>'array','processed_at'=>'datetime'];}
    public function items():HasMany{return $this->hasMany(ChoiceValidationItem::class);}
    public function source():BelongsTo{return $this->belongsTo(ChoiceSource::class,'choice_source_id');}
    public function registration():BelongsTo{return $this->belongsTo(Registration::class,'registration_id');}
}
