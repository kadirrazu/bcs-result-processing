<?php
namespace App\Services\Tabulation;
use App\Models\TabulationProcessingRun;use App\Models\TabulationProcessingState;use App\Models\User;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;
final class TabulationRunService
{
 public function __construct(private readonly TabulationReadinessService $readiness,private readonly TabulationRuleConfig $rules,private readonly TabulationAuditService $audit){}
 public function create(User $actor):TabulationProcessingRun
 {
  $ready=$this->readiness->inspect();if(!$ready['ready'])throw ValidationException::withMessages(['tabulation'=>'Registration, Preliminary, Written and Viva must all be finalized/current before Tabulation can start.']);
  if(TabulationProcessingRun::query()->whereIn('status',['queued','running'])->exists())throw ValidationException::withMessages(['tabulation'=>'A Tabulation generation run is already active.']);
  return DB::connection('exam')->transaction(function()use($actor,$ready){
   $version=((int)TabulationProcessingRun::query()->max('processing_version'))+1;
   $run=TabulationProcessingRun::query()->create(['processing_version'=>$version,'status'=>'queued','source_snapshot'=>$ready['source_snapshot'],'rule_snapshot'=>$this->rules->snapshot(),'created_by'=>$actor->id,'current_step'=>'Queued']);
   $state=TabulationProcessingState::query()->lockForUpdate()->firstOrCreate(['id'=>1],['status'=>'not_started']);
   $before=(string)$state->status;$state->update(['status'=>'queued','latest_run_id'=>$run->id,'is_stale'=>false,'stale_reason'=>null,'source_snapshot'=>$ready['source_snapshot'],'finalized_at'=>null,'finalized_by'=>null]);
   $this->audit->record('TABULATION_GENERATION_QUEUED',$actor,$before,'queued',summary:['processing_version'=>$version,'source_snapshot'=>$ready['source_snapshot']],runId:$run->id);
   return $run;
  });
 }
}
