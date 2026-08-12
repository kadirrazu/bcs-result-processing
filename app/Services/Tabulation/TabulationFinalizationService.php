<?php
namespace App\Services\Tabulation;
use App\Models\TabulationFinalizationRun;use App\Models\TabulationProcessingRun;use App\Models\TabulationProcessingState;use App\Models\TabulationResult;use App\Models\User;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;
final class TabulationFinalizationService
{
 public function __construct(private readonly TabulationReadinessService $readiness,private readonly TabulationAuditService $audit,private readonly TabulationSourceSnapshotComparator $snapshotComparator){}
 public function finalize(User $actor,string $confirmation,?string $notes=null):TabulationFinalizationRun
 {
  if($confirmation!=='FINALIZE')throw ValidationException::withMessages(['confirmation'=>'Type FINALIZE exactly to confirm Tabulation.']);
  return DB::connection('exam')->transaction(function()use($actor,$notes){
   $state=TabulationProcessingState::query()->lockForUpdate()->firstOrCreate(['id'=>1],['status'=>'not_started']);
   if($state->is_stale)throw ValidationException::withMessages(['confirmation'=>'Tabulation is stale. Regenerate it before finalization.']);
   $run=TabulationProcessingRun::query()->lockForUpdate()->whereKey($state->latest_run_id)->first();
   if(!$run||$run->status!=='completed')throw ValidationException::withMessages(['confirmation'=>'Complete Tabulation generation before finalization.']);
   if((int)$run->error_rows>0)throw ValidationException::withMessages(['confirmation'=>'Resolve blocking Tabulation validation errors before finalization.']);
   $ready=$this->readiness->inspect();if(!$ready['ready']||!$this->snapshotComparator->equivalent($run->source_snapshot,$ready['source_snapshot']))throw ValidationException::withMessages(['confirmation'=>'Upstream finalized data changed. Regenerate Tabulation.']);
   $total=TabulationResult::query()->where('processing_run_id',$run->id)->count();if($total!==(int)$run->processed_rows)throw ValidationException::withMessages(['confirmation'=>'Tabulation row count does not match the completed run.']);
   TabulationFinalizationRun::query()->where('status','current')->update(['status'=>'superseded']);
   $summary=array_merge((array)$run->summary,['finalized_at'=>now()->toIso8601String(),'processing_run_id'=>$run->id,'processing_version'=>$run->processing_version]);
   $final=TabulationFinalizationRun::query()->create(['processing_run_id'=>$run->id,'processing_version'=>$run->processing_version,'status'=>'current','source_snapshot'=>$run->source_snapshot,'summary'=>$summary,'finalized_by'=>$actor->id,'finalized_at'=>now(),'notes'=>$notes]);
   $before=(string)$state->status;$state->update(['status'=>'finalized','latest_finalization_run_id'=>$final->id,'finalized_by'=>$actor->id,'finalized_at'=>now(),'summary'=>$summary,'is_stale'=>false,'stale_reason'=>null]);
   $this->audit->record('TABULATION_FINALIZED',$actor,$before,'finalized','Authorized final Tabulation review completed.',$summary,$run->id);
   return $final;
  },3);
 }
}
