<?php
namespace App\Services\ChoiceOptimization;

use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\ChoiceOptimizationGoogleFormBatch;
use App\Models\ChoiceOptimizationGoogleFormRow;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use Illuminate\Support\Facades\DB;

final class ChoiceOptimizationGoogleFormValidationService
{
    public function __construct(private readonly ChoiceValidationFinalizedDatasetService $finalizedChoices) {}

    public function process(int $batchId, int $actorId): ChoiceOptimizationGoogleFormBatch
    {
        $batch = ChoiceOptimizationGoogleFormBatch::query()->findOrFail($batchId);
        $batch->update(['status'=>'validating','processed_rows'=>0,'progress_percent'=>0,'failure_message'=>null]);

        $population = $this->finalizedChoices->choiceReadyResults()->keyBy(fn($r)=>(string)$r->reg);
        $master = collect(CadreMaster::query()->pluck('cadre_abbr'))
            ->merge(CadreSubMaster::query()->pluck('sub_cadre_abbr'))
            ->filter()->map(fn($v)=>mb_strtoupper(trim((string)$v)))->flip();

        $duplicateKeys = ChoiceOptimizationGoogleFormRow::query()->where('batch_id',$batchId)
            ->get(['id','raw_reg','raw_bcs'])
            ->groupBy(fn($r)=>(string)$r->raw_reg.'|'.(string)$r->raw_bcs)
            ->filter(fn($rows)=>$rows->count()>1)->keys()->flip();


        $valid=0; $invalid=0; $processed=0; $total=max(1,(int)$batch->total_rows);
        ChoiceOptimizationGoogleFormRow::query()->where('batch_id',$batchId)->orderBy('id')->chunkById(200,function($rows) use($population,$master,$duplicateKeys,&$valid,&$invalid,&$processed,$total,$batch){
            foreach($rows as $row){
                $errors=[]; $warnings=[]; $registration=null; $bcs=null; $cadre=mb_strtoupper(trim((string)$row->raw_cadre));
                $reg=trim((string)$row->raw_reg); $rawBcs=trim((string)$row->raw_bcs);
                if($reg===''){ $errors[]=['code'=>'REG_REQUIRED','message'=>'Current BCS registration number is required.']; }
                else { $registration=$population->get($reg); if(!$registration){ $errors[]=['code'=>'INVALID_REG_NOT_IN_FINAL_RELEVANT_POPULATION','message'=>'reg is not present in the current BCS final relevant candidate population.']; } }
                if($rawBcs==='' || !ctype_digit($rawBcs) || (int)$rawBcs<1 || (int)$rawBcs>999){ $errors[]=['code'=>'INVALID_BCS','message'=>'bcs must be a positive numeric BCS number.']; } else $bcs=(int)$rawBcs;
                if($cadre===''){ $errors[]=['code'=>'CADRE_REQUIRED','message'=>'cadre is required.']; }
                elseif(!$master->has($cadre)){ $warnings[]=['code'=>'CADRE_NOT_IN_CURRENT_MASTER','message'=>'Cadre abbreviation is not in the current Cadre/Sub-Cadre Master; retained as manually verified historical data.']; }
                if($duplicateKeys->has($reg.'|'.$rawBcs)){ $errors[]=['code'=>'DUPLICATE_REG_BCS_IN_BATCH','message'=>'The same current reg + previous bcs appears more than once in this upload.']; }
                $status=$errors===[]?'valid':'invalid'; $status==='valid'?$valid++:$invalid++;
                $row->update(['registration_id'=>$registration?->registration_id,'current_reg'=>$registration?->reg,'previous_bcs_number'=>$bcs,'cadre'=>$cadre?:null,'validation_status'=>$status,'validation_errors'=>$errors?:null,'validation_warnings'=>$warnings?:null]);
                $processed++;
            }
            $batch->update(['processed_rows'=>$processed,'valid_rows'=>$valid,'invalid_rows'=>$invalid,'progress_percent'=>round(($processed/$total)*100,2)]);
        });
        $batch->update(['status'=>'validated','processed_rows'=>$processed,'valid_rows'=>$valid,'invalid_rows'=>$invalid,'progress_percent'=>100,'validated_by'=>$actorId,'validated_at'=>now(),'finished_at'=>now()]);
        return $batch->refresh();
    }
}
