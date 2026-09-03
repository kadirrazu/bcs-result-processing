<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class AllocationA4SeatLedger extends ExaminationModel
{
    protected $fillable = [
        'allocation_a4_run_id','circular_entry_id','cadre_code','total_capacity','mq_capacity','cff_capacity','em_capacity','phc_capacity',
        'converted_cff','converted_em','converted_phc','merit_capacity','mq_occupied','cff_occupied','em_occupied','phc_occupied',
        'total_occupied','total_remaining','nm_count','shifted_count','quota_to_merit_count',
    ];
    protected function casts(): array { return [
        'cadre_code'=>'integer','total_capacity'=>'integer','mq_capacity'=>'integer','cff_capacity'=>'integer','em_capacity'=>'integer','phc_capacity'=>'integer',
        'converted_cff'=>'integer','converted_em'=>'integer','converted_phc'=>'integer','merit_capacity'=>'integer','mq_occupied'=>'integer','cff_occupied'=>'integer','em_occupied'=>'integer','phc_occupied'=>'integer','total_occupied'=>'integer','total_remaining'=>'integer','nm_count'=>'integer','shifted_count'=>'integer','quota_to_merit_count'=>'integer',
    ]; }
    public function run(): BelongsTo { return $this->belongsTo(AllocationA4Run::class,'allocation_a4_run_id'); }
    public function circularEntry(): BelongsTo { return $this->belongsTo(CircularEntry::class,'circular_entry_id'); }
}
