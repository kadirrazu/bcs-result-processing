<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllocationA5CandidateResult extends ExaminationModel
{
    protected $fillable = [
        'allocation_a5_run_id','allocation_a4_result_id','registration_id','reg','circular_entry_id','cadre_code','cadre_type',
        'allocation_basis','bachelor_status','prs_status','technical_status','quota_status','overall_status','reason_codes',
        'candidate_bachelor_subject_code','candidate_prs_code','allowed_bachelor_subject_codes','allowed_prs_codes',
        'registration_quota_snapshot',
    ];
    protected function casts(): array { return [
        'registration_id'=>'integer','circular_entry_id'=>'integer','cadre_code'=>'integer','reason_codes'=>'array',
        'allowed_bachelor_subject_codes'=>'array','allowed_prs_codes'=>'array','registration_quota_snapshot'=>'array',
    ]; }
    public function run(): BelongsTo { return $this->belongsTo(AllocationA5Run::class,'allocation_a5_run_id'); }
}
