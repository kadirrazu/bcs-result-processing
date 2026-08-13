<?php
namespace App\Services\Merit;
use App\Models\MeritProcessingRun;use App\Models\MeritProcessingState;use App\Models\User;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;
final class MeritRunService
{
 public function __construct(private readonly MeritReadinessService $readiness,private readonly MeritAuditService $audit){}
 public function create(User $actor):MeritProcessingRun
 {
  $ready=$this->readiness->assertReady();
  if(MeritProcessingRun::query()->whereIn('status',['queued','running'])->exists())throw ValidationException::withMessages(['merit'=>'A Merit Generation run is already active.']);
  return DB::connection('exam')->transaction(function()use($actor,$ready){
   $version=((int)MeritProcessingRun::query()->max('processing_version'))+1;
   $run=MeritProcessingRun::query()->create(['processing_version'=>$version,'status'=>'queued','source_snapshot'=>$ready['source_snapshot'],'created_by'=>$actor->id,'current_step'=>'QUEUED']);
   $state=MeritProcessingState::query()->lockForUpdate()->firstOrCreate(['id'=>1],['status'=>'not_started']);$before=(string)$state->status;
   $state->update(['status'=>'queued','latest_run_id'=>$run->id,'is_stale'=>false,'stale_reason'=>null,'source_snapshot'=>$ready['source_snapshot'],'dataset_hash'=>null,'finalized_at'=>null,'finalized_by'=>null]);
   $this->audit->record('MERIT_GENERATION_QUEUED',$actor,$before,'queued',summary:['processing_version'=>$version,'source_snapshot'=>$ready['source_snapshot']],runId:$run->id);
   return $run;
  });
 }
}
