<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllocationA5CapacityResult extends ExaminationModel
{
    protected $fillable = [
        'allocation_a5_run_id','circular_entry_id','cadre_code','sanctioned_posts','allocated_count','remaining_posts','status','reason_code',
    ];
    protected function casts(): array { return [
        'circular_entry_id'=>'integer','cadre_code'=>'integer','sanctioned_posts'=>'integer','allocated_count'=>'integer','remaining_posts'=>'integer',
    ]; }
    public function run(): BelongsTo { return $this->belongsTo(AllocationA5Run::class,'allocation_a5_run_id'); }
}
