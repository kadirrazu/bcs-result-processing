<?php
namespace App\Services\Viva;
use App\Models\User;
use App\Models\VivaFinalizationRun;
use App\Models\VivaProcessingRun;
use App\Models\VivaProcessingState;
use App\Models\VivaResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VivaFinalizationService
{
    public function __construct(private readonly VivaAuditService $audit,private readonly VivaRuleConfig $rules,private readonly \App\Services\Dependencies\DownstreamStalePropagationService $downstream){}
    public function finalize(User $actor,string $confirmation,?string $notes=null):VivaFinalizationRun
    {
        if($confirmation!=='FINALIZE'){throw ValidationException::withMessages(['confirmation'=>'Type FINALIZE exactly to confirm the final Viva checkpoint.']);}
        return DB::connection('exam')->transaction(function()use($actor,$notes):VivaFinalizationRun{
            $state=VivaProcessingState::query()->lockForUpdate()->firstOrCreate(['id'=>1],['status'=>'not_started']);
            if($state->is_stale){throw ValidationException::withMessages(['confirmation'=>'Viva processing is outdated. Regenerate reconciliation and process the result again.']);}
            if(!$state->result_processed_at){throw ValidationException::withMessages(['confirmation'=>'Complete Viva result processing before finalization.']);}
            $latestRun=VivaProcessingRun::query()->lockForUpdate()->latest('id')->first();
            if(!$latestRun||$latestRun->status!=='completed'){throw ValidationException::withMessages(['confirmation'=>'The latest Viva result processing run is not completed.']);}
            if(VivaProcessingRun::query()->whereIn('status',['queued','running'])->exists()){throw ValidationException::withMessages(['confirmation'=>'A Viva result processing run is still active.']);}
            $total=VivaResult::query()->count();
            $processed=VivaResult::query()->whereNotNull('processing_run_id')->whereNotNull('viva_result_status')->count();
            if($total===0||$processed!==$total){throw ValidationException::withMessages(['confirmation'=>sprintf('Viva processing is incomplete: %d of %d records have a derived result.',$processed,$total)]);}
            $summary=[
                'total_records'=>$total,
                'active'=>VivaResult::query()->where('status','active')->count(),
                'cancelled'=>VivaResult::query()->where('status','cancelled')->count(),
                'withheld'=>VivaResult::query()->where('status','withheld')->count(),
                'expelled'=>VivaResult::query()->where('status','expelled')->count(),
                'appeared'=>VivaResult::query()->where('attendance_status','appeared')->count(),
                'absent'=>VivaResult::query()->where('attendance_status','absent')->count(),
                'pass'=>VivaResult::query()->where('viva_result_status','pass')->count(),
                'fail'=>VivaResult::query()->where('viva_result_status','fail')->count(),
                'not_applicable'=>VivaResult::query()->where('viva_result_status','not_applicable')->count(),
                'warnings'=>VivaResult::query()->where(fn($q)=>$q->where('validation_status','warning')->orWhere('quota_mismatch',true)->orWhere('invalid_flag',true)->orWhere('issue_flag',true)->orWhere('high_mark_review',true))->count(),
                'full_mark'=>$this->rules->fullMark(),'pass_percent'=>$this->rules->passPercent(),'pass_mark'=>$this->rules->passMark(),
                'processing_run_id'=>$latestRun->id,'processing_version'=>$latestRun->processing_version,'finalized_at'=>now()->toIso8601String(),
            ];
            VivaFinalizationRun::query()->where('status','current')->update(['status'=>'superseded']);
            $finalization=VivaFinalizationRun::query()->create(['processing_run_id'=>$latestRun->id,'processing_version'=>$latestRun->processing_version,'status'=>'current','summary'=>$summary,'finalized_by'=>$actor->id,'finalized_at'=>now(),'notes'=>$notes]);
            $before=$state->status instanceof \BackedEnum?$state->status->value:(string)$state->status;
            $state->update(['status'=>'result_finalized','result_finalized_by'=>$actor->id,'result_finalized_at'=>now(),'summary'=>array_merge((array)$state->summary,['finalization_run_id'=>$finalization->id,'finalization'=>$summary]),'is_stale'=>false,'stale_reason'=>null]);
            $this->audit->record('VIVA_RESULT_FINALIZED',$actor,$before,'result_finalized','Authorized final Viva review completed.',summary:['finalization_run_id'=>$finalization->id,'processing_run_id'=>$latestRun->id,'processing_version'=>$latestRun->processing_version,'counts'=>$summary,'confidential_output'=>true]);
            $this->downstream->propagate(
                'viva',
                'Viva result was finalized/re-finalized. Tabulation and Choice Validation generated from an older Viva result must be regenerated.',
                (int) $actor->id,
            );
            return $finalization;
        },3);
    }
}
