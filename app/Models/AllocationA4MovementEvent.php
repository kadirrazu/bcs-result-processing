<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class AllocationA4MovementEvent extends ExaminationModel
{
    public $timestamps = false;
    protected $fillable = [
        'allocation_a4_run_id','sequence_no','actor_id','iteration_no','event','registration_id','from_circular_entry_id','from_cadre_code','from_basis','from_choice_position',
        'to_circular_entry_id','to_cadre_code','to_basis','to_choice_position','target_merit_position','movement_type','reason','converted_from','context','created_at',
    ];
    protected function casts(): array { return ['context'=>'array','created_at'=>'datetime']; }
    public function run(): BelongsTo { return $this->belongsTo(AllocationA4Run::class,'allocation_a4_run_id'); }
}
