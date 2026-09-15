<?php
namespace App\Services\Merit;
use App\Services\Choice\ChoiceWrittenTrackProjectionService;use App\Models\CadreMaster;use App\Models\CadreSubMaster;use App\Models\CircularEntry;use App\Models\MeritCadreRank;use App\Models\MeritProcessingRun;use App\Models\MeritProcessingState;use App\Models\MeritResult;use App\Models\Registration;use App\Models\TabulationResult;use Illuminate\Support\Facades\DB;use RuntimeException;use Throwable;
final class MeritGenerationService
{
 public function __construct(private readonly MeritReadinessService $readiness,private readonly MeritSourceSnapshotComparator $snapshots,private readonly MeritRankingService $ranking,private readonly MeritDatasetHasher $hasher,private readonly ChoiceWrittenTrackProjectionService $trackProjection){}
 public function process(int $runId):MeritProcessingRun
 {
  $run=MeritProcessingRun::query()->findOrFail($runId);$ready=$this->readiness->assertReady();
  if(!$this->snapshots->equivalent($run->source_snapshot,$ready['source_snapshot']))throw new RuntimeException('MERIT_SOURCE_HASH_SNAPSHOT_CHANGED: Circular or Tabulation changed after this run was queued.');
  $state=MeritProcessingState::query()->firstOrCreate(['id'=>1],['status'=>'not_started']);$run->update(['status'=>'running','started_at'=>now(),'current_step'=>'RANKING_GLOBAL_MERIT','failure_message'=>null]);$state->update(['status'=>'running','latest_run_id'=>$run->id,'source_snapshot'=>$run->source_snapshot,'is_stale'=>false,'stale_reason'=>null]);
  try{
   MeritCadreRank::query()->where('processing_run_id',$run->id)->delete();MeritResult::query()->where('processing_run_id',$run->id)->delete();
   $tabRun=(int)$run->source_snapshot['tabulation']['processing_run_id'];$rows=TabulationResult::query()->where('processing_run_id',$tabRun)->orderBy('id')->get();$run->update(['total_rows'=>$rows->count()]);
   $common=$this->ranking->rank($rows,'common');$general=$this->ranking->rank($rows,'general');$technical=$this->ranking->rank($rows,'technical');$now=now();$insert=[];
   foreach($rows as $r){$commonEligible=isset($common[$r->id]);$reason=$commonEligible?null:((bool)$r->general_merit_eligible||(bool)$r->technical_merit_eligible?null:'NOT_MERIT_ELIGIBLE');$insert[]=['processing_run_id'=>$run->id,'processing_version'=>$run->processing_version,'tabulation_result_id'=>$r->id,'registration_id'=>$r->registration_id,'user_id'=>$r->user_id,'reg'=>$r->reg,'cadre_category'=>$r->cadre_category,'written_qualified_track'=>$r->written_qualified_track,'graduation_year'=>$r->graduation_year,'common_merit_position'=>$common[$r->id]??null,'general_merit_position'=>$general[$r->id]??null,'technical_merit_position'=>$technical[$r->id]??null,'all_merit_tech'=>json_encode([]),'common_merit_eligible'=>$commonEligible,'general_merit_eligible'=>(bool)$r->general_merit_eligible,'technical_merit_eligible'=>(bool)$r->technical_merit_eligible,'status_reason'=>$reason,'processed_at'=>$now,'created_at'=>$now,'updated_at'=>$now];}
   foreach(array_chunk($insert,max(100,(int)config('merit.insert_chunk_size',1000))) as $chunk)DB::connection('exam')->table('merit_results')->insert($chunk);
   $run->update(['processed_rows'=>$rows->count(),'common_ranked_count'=>count($common),'general_ranked_count'=>count($general),'technical_ranked_count'=>count($technical),'progress_percent'=>60,'current_step'=>'BUILDING_TECHNICAL_CADRE_WISE_MERIT']);

   // Revised authority: cadre-wise merit exists only for Technical cadres and is based on
   // Technical Merit + finalized Circular academic eligibility. Candidate choice is intentionally
   // NOT a Merit dependency; Final Allocation Ready Choice decides actual competition later.
   $circularVersion=(int)$run->source_snapshot['circular']['version'];
   $entries=CircularEntry::query()->with(['bachelorSubjects','prsSubjects'])->where('version',$circularVersion)->where('status','active')->get();
   $technicalEntries=$entries->filter(fn($entry):bool=>(string)($entry->cadre_type?->value??$entry->cadre_type)==='TT')->groupBy(fn($entry)=>(string)$entry->effective_code);
   $main=CadreMaster::query()->get(['cadre_code','cadre_abbr'])->keyBy(fn($m)=>(string)$m->cadre_code);$sub=CadreSubMaster::query()->get(['sub_cadre_code','sub_cadre_abbr'])->keyBy(fn($m)=>(string)$m->sub_cadre_code);
   $merits=MeritResult::query()->where('processing_run_id',$run->id)->whereNotNull('technical_merit_position')->get()->keyBy('registration_id');
   $registrations=Registration::query()->whereIn('id',$merits->keys())->get(['id','bachelor_subject_code','post_related_subject_code'])->keyBy('id');
   $candidatesByCadre=[];
   foreach($merits as $m){
    if(!$this->trackProjection->allows((string)$m->written_qualified_track,'TT'))continue;
    $registration=$registrations->get((int)$m->registration_id);if(!$registration)continue;
    foreach($technicalEntries as $code=>$entryGroup){$entry=$entryGroup->first(fn($candidateEntry):bool=>$this->eligibleTechnicalEntry($candidateEntry,$registration));if(!$entry)continue;$abbr=$sub->get((string)$code)?->sub_cadre_abbr??$main->get((string)$code)?->cadre_abbr??('CODE_'.$code);$candidatesByCadre[(string)$code][]=['merit'=>$m,'entry'=>$entry,'abbr'=>$abbr,'source'=>(int)$m->technical_merit_position];}
   }
   $cadreRows=[];$allTech=[];foreach($candidatesByCadre as $code=>$items){usort($items,fn($a,$b)=>[$a['source'],$a['merit']->reg]<=>[$b['source'],$b['merit']->reg]);$pos=0;foreach($items as $item){$pos++;$m=$item['merit'];$cadreRows[]=['processing_run_id'=>$run->id,'processing_version'=>$run->processing_version,'merit_result_id'=>$m->id,'registration_id'=>$m->registration_id,'circular_entry_id'=>$item['entry']->id,'cadre_code'=>(int)$code,'cadre_abbr'=>$item['abbr'],'cadre_type'=>'TT','cadre_merit_position'=>$pos,'source_merit_position'=>$item['source'],'choice_position'=>null,'qualification_basis'=>'TECHNICAL_MERIT_AND_CIRCULAR_ELIGIBILITY','created_at'=>$now];$allTech[$m->id][$item['abbr']]=$pos;}}
   foreach(array_chunk($cadreRows,max(100,(int)config('merit.insert_chunk_size',1000))) as $chunk)if($chunk)DB::connection('exam')->table('merit_cadre_ranks')->insert($chunk);
   foreach($allTech as $meritId=>$map)MeritResult::query()->whereKey($meritId)->update(['all_merit_tech'=>$map]);
   $tieSummary=[];foreach(['common','general','technical'] as $scope){$groups=$this->ranking->businessTieGroups($rows,$scope);$tieSummary[$scope]=['groups'=>count($groups),'candidates'=>array_sum(array_map('count',$groups))];}
   $hash=$this->hasher->hash($run->id);$summary=['total_rows'=>$rows->count(),'common_ranked_count'=>count($common),'general_ranked_count'=>count($general),'technical_ranked_count'=>count($technical),'cadre_rank_rows'=>count($cadreRows),'cadre_count'=>count($candidatesByCadre),'tie_review'=>$tieSummary,'dataset_hash'=>$hash];$run->update(['status'=>'completed','cadre_rank_rows'=>count($cadreRows),'dataset_hash'=>$hash,'summary'=>$summary,'progress_percent'=>100,'current_step'=>'GENERATION_COMPLETED_REVIEW_AND_FINALIZE','finished_at'=>now()]);$state->update(['status'=>'review_ready','summary'=>$summary,'dataset_hash'=>$hash,'is_stale'=>false,'stale_reason'=>null]);return $run->refresh();
  }catch(Throwable $e){$run->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,65000),'finished_at'=>now()]);$state->update(['status'=>'failed','is_stale'=>true,'stale_reason'=>'MERIT_GENERATION_FAILED: '.$e->getMessage()]);throw $e;}
 }
 private function eligibleTechnicalEntry(CircularEntry $entry,Registration $registration):bool
 {
  $bachelor=$entry->bachelorSubjects->pluck('subject_code')->map(fn($v)=>(string)$v)->all();$prs=$entry->prsSubjects->pluck('prs_code')->map(fn($v)=>(string)$v)->all();
  return in_array((string)$registration->bachelor_subject_code,$bachelor,true)&&in_array((string)$registration->post_related_subject_code,$prs,true);
 }
}
