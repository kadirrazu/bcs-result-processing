<?php
namespace App\Jobs;
use App\Models\Examination;use App\Models\TabulationProcessingRun;use App\Services\Tabulation\TabulationGenerationService;use App\Support\Examinations\ExaminationConnectionManager;use Illuminate\Bus\Queueable;use Illuminate\Contracts\Queue\ShouldQueue;use Illuminate\Foundation\Bus\Dispatchable;use Illuminate\Queue\InteractsWithQueue;use Illuminate\Queue\SerializesModels;use Throwable;
final class ProcessTabulation implements ShouldQueue
{
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;public int $tries=1;public int $timeout=0;
 public function __construct(public readonly int $examinationId,public readonly int $runId){$this->onQueue((string)config('tabulation.queue','imports'));}
 public function handle(ExaminationConnectionManager $connections,TabulationGenerationService $service):void
 {
  $exam=Examination::query()->findOrFail($this->examinationId);$connections->configure($exam);
  try{$service->process($this->runId);}catch(Throwable $e){TabulationProcessingRun::query()->whereKey($this->runId)->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,65000),'finished_at'=>now()]);throw $e;}finally{$connections->disconnect();}
 }
}
