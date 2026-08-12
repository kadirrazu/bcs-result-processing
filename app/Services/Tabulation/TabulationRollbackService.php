<?php
namespace App\Services\Tabulation;
use App\Models\TabulationFinalizationRun;use App\Models\TabulationProcessingRun;use App\Models\TabulationProcessingState;use App\Models\TabulationResult;use App\Models\User;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;
final class TabulationRollbackService
{
 public function __construct(private readonly TabulationReadinessService $readiness,private readonly TabulationAuditService $audit,private readonly TabulationSourceSnapshotComparator $snapshotComparator){}
 public function rollback(TabulationFinalizationRun $target,User $actor,string $confirmation,?string $reason=null):TabulationFinalizationRun
 {
  if($confirmation!=='ROLLBACK')throw ValidationException::withMessages(['confirmation'=>'Type ROLLBACK exactly to restore this finalized Tabulation version.']);
  $ready=$this->readiness->inspect();if(!$ready['ready']||!$this->snapshotComparator->equivalent($target->source_snapshot,$ready['source_snapshot']))throw ValidationException::withMessages(['confirmation'=>'This historical Tabulation was produced from different upstream source versions. Re-generation is mandatory; rollback is not allowed.']);
  if(TabulationProcessingRun::query()->whereIn('status',['queued','running'])->exists())throw ValidationException::withMessages(['confirmation'=>'A Tabulation generation run is active. Complete it before rollback.']);
  $run=TabulationProcessingRun::query()->findOrFail($target->processing_run_id);if($run->status!=='completed'||(int)$run->error_rows>0)throw ValidationException::withMessages(['confirmation'=>'Only a completed, error-free historical Tabulation can be restored.']);
  $count=TabulationResult::query()->where('processing_run_id',$run->id)->count();if($count!==(int)$run->processed_rows)throw ValidationException::withMessages(['confirmation'=>'Historical Tabulation rows are incomplete and cannot be restored.']);
  return DB::connection('exam')->transaction(function()use($target,$actor,$reason,$run){
   $state=TabulationProcessingState::query()->lockForUpdate()->firstOrCreate(['id'=>1],['status'=>'not_started']);$before=$state->status;
   TabulationFinalizationRun::query()->where('status','current')->update(['status'=>'superseded']);$target->update(['status'=>'current']);
   $state->update(['status'=>'finalized','latest_run_id'=>$run->id,'latest_finalization_run_id'=>$target->id,'is_stale'=>false,'stale_reason'=>null,'source_snapshot'=>$target->source_snapshot,'summary'=>$target->summary,'finalized_by'=>$actor->id,'finalized_at'=>now()]);
   $this->audit->record('TABULATION_ROLLED_BACK',$actor,(string)$before,'finalized',$reason?:'Restored a compatible historical finalized Tabulation version.',['restored_finalization_run_id'=>$target->id,'processing_run_id'=>$run->id,'processing_version'=>$run->processing_version],$run->id);return $target->refresh();
  },3);
 }
}
