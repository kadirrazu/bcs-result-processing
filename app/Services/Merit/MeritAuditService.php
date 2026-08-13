<?php
namespace App\Services\Merit;
use App\Models\MeritProcessingAudit;use App\Models\User;
final class MeritAuditService
{
 public function record(string $event,?User $actor,?string $from=null,?string $to=null,?string $reason=null,array $summary=[],?int $runId=null):void
 {MeritProcessingAudit::query()->create(['event'=>$event,'processing_run_id'=>$runId,'actor_id'=>$actor?->id,'from_status'=>$from,'to_status'=>$to,'reason'=>$reason,'summary'=>$summary?:null,'created_at'=>now()]);}
}
