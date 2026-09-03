<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class AllocationA4Result extends ExaminationModel
{
    protected $fillable = [
        'allocation_a4_run_id','input_candidate_id','registration_id','reg','circular_entry_id','cadre_code','cadre_type',
        'choice_position','merit_position','merit_source','allocation_basis','movement_type','decision_status','decision_reason',
        'original_circular_entry_id','original_cadre_code','original_choice_position','original_allocation_basis',
    ];
    protected function casts(): array { return ['registration_id'=>'integer','cadre_code'=>'integer','choice_position'=>'integer','merit_position'=>'integer','original_cadre_code'=>'integer','original_choice_position'=>'integer']; }
    public function run(): BelongsTo { return $this->belongsTo(AllocationA4Run::class,'allocation_a4_run_id'); }
    public function circularEntry(): BelongsTo { return $this->belongsTo(CircularEntry::class,'circular_entry_id'); }
}
