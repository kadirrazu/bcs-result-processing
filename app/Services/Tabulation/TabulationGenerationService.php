<?php
namespace App\Services\Tabulation;

use App\Models\TabulationProcessingRun;
use App\Models\TabulationProcessingState;
use App\Models\TabulationResult;
use App\Services\Written\WrittenSubjectConfig;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class TabulationGenerationService
{
    public function __construct(
        private readonly TabulationReadinessService $readiness,
        private readonly TabulationRuleConfig $rules,
        private readonly WrittenSubjectConfig $writtenConfig,
        private readonly TabulationSourceSnapshotComparator $snapshotComparator,
        private readonly TabulationDatasetHasher $datasetHasher,
    ){}

    public function process(int $runId):TabulationProcessingRun
    {
        $run=TabulationProcessingRun::query()->findOrFail($runId);
        $ready=$this->readiness->inspect();
        if(!$ready['ready']){
            throw new RuntimeException('Required upstream modules are no longer finalized/current. Tabulation cannot run.');
        }
        if(! $this->snapshotComparator->equivalent($run->source_snapshot, $ready['source_snapshot'])){
            throw new RuntimeException('Upstream source versions changed after this Tabulation run was queued. Queue a new run.');
        }

        $run->update(['status'=>'running','started_at'=>now(),'current_step'=>'Preparing finalized APPEARED Viva population','failure_message'=>null]);
        $state=TabulationProcessingState::query()->updateOrCreate(['id'=>1],['status'=>'running','latest_run_id'=>$run->id,'is_stale'=>false,'stale_reason'=>null,'source_snapshot'=>$run->source_snapshot]);

        try{
            TabulationResult::query()->where('processing_run_id',$run->id)->delete();
            $population=DB::connection('exam')->table('viva_results as v')
                ->join('written_results as w','w.id','=','v.written_result_id')
                ->join('registrations as r','r.id','=','v.registration_id')
                ->leftJoin('preliminary_results as p','p.registration_id','=','v.registration_id')
                ->where('v.attendance_status','appeared')
                ->where('v.status','active')
                ->select([
                    'v.id as viva_result_id','v.registration_id','v.written_result_id','v.user_id','v.reg','v.mark as viva_mark','v.viva_result_status',
                    'r.cadre_category','r.birth_date',
                    'w.written_qualified_track','w.general_result_status','w.technical_result_status','w.general_counted_total','w.technical_counted_total','w.finalized_at as written_finalized_at',
                    'p.id as preliminary_result_id','p.mark as preliminary_mark','p.finalized_at as preliminary_finalized_at',
                ]);
            $total=(clone $population)->count();
            $run->update(['total_rows'=>$total,'current_step'=>'Generating derived Tabulation rows']);
            $done=$valid=$warning=$error=$generalPass=$technicalPass=$generalEligible=$technicalEligible=0;
            $rule=$this->rules->snapshot();

            $population->orderBy('v.id')->chunkById($this->rules->processingChunkSize(),function($rows)use($run,$total,$rule,&$done,&$valid,&$warning,&$error,&$generalPass,&$technicalPass,&$generalEligible,&$technicalEligible):void{
                $writtenIds=$rows->pluck('written_result_id')->all();
                $marks=DB::connection('exam')->table('written_candidate_marks')->whereIn('written_result_id',$writtenIds)->where('is_applicable',true)->get()->groupBy('written_result_id');
                $insert=[];$now=now();
                foreach($rows as $row){
                    $errors=[];$warnings=[];
                    $generalPf=$this->pf($row->general_result_status);
                    $technicalPf=$this->pf($row->technical_result_status);
                    $vivaPass=$row->viva_result_status==='pass';
                    $generalTotal=$row->general_counted_total!==null?(float)$row->general_counted_total:null;
                    $technicalTotal=$row->technical_counted_total!==null?(float)$row->technical_counted_total:null;
                    $viva=(float)$row->viva_mark;
                    $track=strtoupper(trim((string)$row->written_qualified_track));
                    $generalTrackSurvives=in_array($track,['GG','GN','GT'],true);
                    $technicalTrackSurvives=in_array($track,['TT','T','GT'],true);
                    $generalGrand=$generalTrackSurvives&&$generalPf==='PASS'&&$generalTotal!==null?$generalTotal+$viva:null;
                    $technicalGrand=$technicalTrackSurvives&&$technicalPf==='PASS'&&$technicalTotal!==null?$technicalTotal+$viva:null;
                    $generalMerit=$generalPf==='PASS'&&$vivaPass;
                    $technicalMerit=$technicalPf==='PASS'&&$vivaPass;

                    if($row->birth_date===null)$errors[]='MERIT_TIE_BREAK_BIRTH_DATE_MISSING';
                    if($row->cadre_category===null)$errors[]='CADRE_CATEGORY_MISSING';

                    if($row->preliminary_result_id===null||$row->preliminary_finalized_at===null)$errors[]='FINALIZED_PRELIMINARY_SOURCE_MISSING';
                    if($row->written_finalized_at===null)$errors[]='FINALIZED_WRITTEN_SOURCE_MISSING';
                    if($generalTrackSurvives&&$generalPf!=='PASS')$errors[]='QUALIFIED_TRACK_GENERAL_PF_INCONSISTENT';
                    if($technicalTrackSurvives&&$technicalPf!=='PASS')$errors[]='QUALIFIED_TRACK_TECHNICAL_PF_INCONSISTENT';
                    if($viva<0||$viva>$rule['viva_full_mark'])$errors[]='VIVA_MARK_EXCEEDS_CONFIGURED_RANGE';
                    if($generalTotal!==null&&($generalTotal<0||$generalTotal>$rule['general_written_full_mark']))$errors[]='GENERAL_WRITTEN_TOTAL_EXCEEDS_CONFIGURED_RANGE';
                    if($technicalTotal!==null&&($technicalTotal<0||$technicalTotal>$rule['technical_written_full_mark']))$errors[]='TECHNICAL_WRITTEN_TOTAL_EXCEEDS_CONFIGURED_RANGE';
                    if($generalGrand!==null&&$generalGrand>$rule['general_grand_full_mark'])$errors[]='GENERAL_GRAND_TOTAL_EXCEEDS_CONFIGURED_RANGE';
                    if($technicalGrand!==null&&$technicalGrand>$rule['technical_grand_full_mark'])$errors[]='TECHNICAL_GRAND_TOTAL_EXCEEDS_CONFIGURED_RANGE';

                    foreach($marks->get($row->written_result_id,collect()) as $mark){
                        if($mark->actual_mark===null)continue;
                        try{$full=$this->writtenConfig->fullMark((string)$mark->subject_code);}catch(Throwable){continue;}
                        if((float)$mark->actual_mark<0||(float)$mark->actual_mark>$full)$errors[]='INDIVIDUAL_MARK_EXCEEDS_CONFIGURED_RANGE:'.$mark->subject_code;
                    }

                    $review=$rule['grand_total_review_percent']/100;
                    if($generalGrand!==null&&$generalGrand>=$rule['general_grand_full_mark']*$review)$warnings[]='GENERAL_GRAND_TOTAL_HIGH_REVIEW';
                    if($technicalGrand!==null&&$technicalGrand>=$rule['technical_grand_full_mark']*$review)$warnings[]='TECHNICAL_GRAND_TOTAL_HIGH_REVIEW';
                    $status=$errors!==[]?'error':($warnings!==[]?'warning':'valid');
                    $status==='error'?$error++:($status==='warning'?$warning++:$valid++);
                    if($generalPf==='PASS')$generalPass++;if($technicalPf==='PASS')$technicalPass++;
                    if($generalMerit)$generalEligible++;if($technicalMerit)$technicalEligible++;
                    $insert[]=[
                        'processing_run_id'=>$run->id,'processing_version'=>$run->processing_version,'registration_id'=>$row->registration_id,'preliminary_result_id'=>$row->preliminary_result_id,
                        'written_result_id'=>$row->written_result_id,'viva_result_id'=>$row->viva_result_id,'user_id'=>$row->user_id,'reg'=>$row->reg,'cadre_category'=>$row->cadre_category,'birth_date'=>$row->birth_date,'written_qualified_track'=>$row->written_qualified_track,
                        'preliminary_mark'=>$row->preliminary_mark,'general_written_total'=>$generalTotal,'technical_written_total'=>$technicalTotal,'viva_mark'=>$viva,'general_grand_total'=>$generalGrand,'technical_grand_total'=>$technicalGrand,
                        'general_pf'=>$generalPf,'technical_pf'=>$technicalPf,'general_merit_eligible'=>$generalMerit,'technical_merit_eligible'=>$technicalMerit,'validation_status'=>$status,
                        'validation_errors'=>$errors?json_encode(array_values(array_unique($errors))):null,'review_warnings'=>$warnings?json_encode(array_values(array_unique($warnings))):null,
                        'source_snapshot'=>json_encode(['preliminary_result_id'=>$row->preliminary_result_id,'written_result_id'=>$row->written_result_id,'viva_result_id'=>$row->viva_result_id]),
                        'processing_flags'=>json_encode(['viva_pass'=>$vivaPass,'general_track_applicable'=>$generalTrackSurvives,'technical_track_applicable'=>$technicalTrackSurvives,'general_track_failed'=>$track==='T','technical_track_failed'=>$track==='GN']),
                        'processed_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
                    ];
                    $done++;
                }
                if($insert!==[])DB::connection('exam')->table('tabulation_results')->insert($insert);
                $run->update(['processed_rows'=>$done,'valid_rows'=>$valid,'warning_rows'=>$warning,'error_rows'=>$error,'general_pass_count'=>$generalPass,'technical_pass_count'=>$technicalPass,'general_merit_eligible_count'=>$generalEligible,'technical_merit_eligible_count'=>$technicalEligible,'progress_percent'=>$total?min(99.9,round($done/$total*100,4)):100]);
            },'v.id','viva_result_id');

            $datasetHash=$this->datasetHasher->hash((int)$run->id);
            $summary=['total_rows'=>$total,'valid_rows'=>$valid,'warning_rows'=>$warning,'error_rows'=>$error,'general_pass_count'=>$generalPass,'technical_pass_count'=>$technicalPass,'general_merit_eligible_count'=>$generalEligible,'technical_merit_eligible_count'=>$technicalEligible,'dataset_hash'=>$datasetHash];
            $run->update(['status'=>'completed','dataset_hash'=>$datasetHash,'processed_rows'=>$done,'progress_percent'=>100,'current_step'=>'Generation completed; review and finalize','summary'=>$summary,'finished_at'=>now()]);
            $state->update(['status'=>$error>0?'needs_review':'review_ready','summary'=>$summary,'source_snapshot'=>$run->source_snapshot,'dataset_hash'=>$datasetHash,'is_stale'=>false,'stale_reason'=>null]);
            return $run->refresh();
        }catch(Throwable $e){
            $run->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,65000),'finished_at'=>now()]);
            $state->update(['status'=>'failed','is_stale'=>true,'stale_reason'=>'The latest Tabulation generation failed and must be regenerated.']);
            throw $e;
        }
    }

    private function pf(?string $status):string{return match($status){'pass'=>'PASS','fail'=>'FAIL',default=>'N/A'};}
}
