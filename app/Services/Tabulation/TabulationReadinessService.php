<?php
namespace App\Services\Tabulation;
use App\Models\PreliminaryProcessingState;
use App\Models\Registration;
use App\Models\RegistrationImportBatch;
use App\Models\VivaFinalizationRun;
use App\Models\VivaProcessingState;
use App\Models\WrittenProcessingState;

final class TabulationReadinessService
{
    public function inspect():array
    {
        $registrationCount=Registration::query()->count();
        $activeRegistrationImports=RegistrationImportBatch::query()->whereIn('status',['uploaded','queued','staging','validating','validated','approving'])->count();
        $preliminary=PreliminaryProcessingState::query()->first();
        $written=WrittenProcessingState::query()->first();
        $viva=VivaProcessingState::query()->first();
        $vivaFinal=VivaFinalizationRun::query()->where('status','current')->latest('id')->first();
        $checks=[
            'registration'=>[
                'ready'=>$registrationCount>0&&$activeRegistrationImports===0,
                'label'=>'Registration',
                'detail'=>$registrationCount>0?($activeRegistrationImports===0?'Authoritative approved Registration dataset is ready.':'A Registration import/approval operation is still active.'):'No approved Registration dataset exists.',
            ],
            'preliminary'=>[
                'ready'=>(bool)$preliminary?->result_finalized_at,
                'label'=>'Preliminary',
                'detail'=>$preliminary?->result_finalized_at?'Finalized at '.$preliminary->result_finalized_at->format('Y-m-d H:i:s'):'Preliminary is not finalized.',
            ],
            'written'=>[
                'ready'=>(bool)$written?->result_finalized_at&&!(bool)$written?->is_stale,
                'label'=>'Written',
                'detail'=>$written?->result_finalized_at?((bool)$written?->is_stale?'Written is stale and must be re-finalized.':'Finalized at '.$written->result_finalized_at->format('Y-m-d H:i:s')):'Written is not finalized.',
            ],
            'viva'=>[
                'ready'=>(bool)$viva?->result_finalized_at&&!(bool)$viva?->is_stale&&$vivaFinal!==null,
                'label'=>'Viva',
                'detail'=>$viva?->result_finalized_at?((bool)$viva?->is_stale?'Viva is stale and must be re-finalized.':'Finalized at '.$viva->result_finalized_at->format('Y-m-d H:i:s')):'Viva is not finalized.',
            ],
        ];
        return ['ready'=>collect($checks)->every(fn(array $c)=>$c['ready']),'checks'=>$checks,'source_snapshot'=>$this->sourceSnapshot($registrationCount,$preliminary,$written,$viva,$vivaFinal)];
    }

    private function sourceSnapshot(int $registrationCount,$preliminary,$written,$viva,$vivaFinal):array
    {
        $latestRegistration=RegistrationImportBatch::query()->whereIn('status',['approved','completed','completed_with_errors'])->latest('id')->first();
        return [
            'registration'=>['count'=>$registrationCount,'latest_approved_batch_id'=>$latestRegistration?->id,'approved_at'=>$latestRegistration?->approved_at?->toIso8601String(),'max_updated_at'=>Registration::query()->max('updated_at')],
            'preliminary'=>['state_status'=>$preliminary?->status?->value??(string)($preliminary?->status??''),'finalized_at'=>$preliminary?->result_finalized_at?->toIso8601String(),'finalization_run_id'=>$preliminary?->latest_finalization_run_id,'count'=>\App\Models\PreliminaryResult::query()->count(),'max_updated_at'=>\App\Models\PreliminaryResult::query()->max('updated_at')],
            'written'=>['state_status'=>$written?->status?->value??(string)($written?->status??''),'finalized_at'=>$written?->result_finalized_at?->toIso8601String(),'processing_run_id'=>$written?->latest_processing_run_id,'count'=>\App\Models\WrittenResult::query()->count(),'max_updated_at'=>\App\Models\WrittenResult::query()->max('updated_at')],
            'viva'=>['state_status'=>$viva?->status?->value??(string)($viva?->status??''),'finalized_at'=>$viva?->result_finalized_at?->toIso8601String(),'processing_run_id'=>$viva?->latest_processing_run_id,'finalization_run_id'=>$vivaFinal?->id,'appeared_count'=>\App\Models\VivaResult::query()->where('attendance_status','appeared')->where('status','active')->count(),'max_updated_at'=>\App\Models\VivaResult::query()->max('updated_at')],
        ];
    }
}
