<?php

namespace App\Services\NonCadre\Allocation;

use App\Models\AllocationA5CandidateResult;
use App\Models\AllocationA5Run;
use App\Models\BachelorSubject;
use App\Models\Gender;
use App\Services\Merit\MeritFinalizedDatasetService;
use App\Services\NonCadre\NonCadreReadinessService;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class NonCadreAllocationService
{
    public function __construct(private readonly NonCadreReadinessService $readiness, private readonly MeritFinalizedDatasetService $merit) {}

    public function prerequisites(): array
    {
        $g=$this->readiness->inspect(); if(!($g['ready']??false))return ['ready'=>false,'reason'=>$g['reason']??'Cadre Allocation is not current.'];
        $c=$this->current('non_cadre_circular_versions'); if(!$c)return ['ready'=>false,'reason'=>'Finalize NC1 — Non-Cadre Circular.'];
        $s=$this->current('non_cadre_seat_breakup_versions'); if(!$s||(int)$s->circular_version_id!==(int)$c->id)return ['ready'=>false,'reason'=>'Finalize/current NC2 — Seat Breakup is required.'];
        $q=$this->current('non_cadre_choice_imports'); if(!$q||(int)$q->circular_version_id!==(int)$c->id)return ['ready'=>false,'reason'=>'Finalize/current NC3 — Allocation Ready Choice is required.'];
        return ['ready'=>true,'reason'=>null,'gate'=>$g,'circular'=>$c,'seat'=>$s,'choice'=>$q];
    }

    /** Queue NC4.1 quickly; the worker materializes the immutable snapshot. */
    public function enqueueFreeze(?int $actor): object
    {
        $p=$this->requirePrerequisites();
        $a5=AllocationA5Run::query()->where('status','finalized')->where('is_stale',false)->latest('version')->firstOrFail();
        return DB::connection('exam')->transaction(function()use($p,$a5,$actor){
            DB::connection('exam')->table('non_cadre_allocation_runs')->where('is_stale',false)->update(['is_stale'=>true,'stale_reason'=>'Superseded by newer NC4 Input Freeze.','staled_at'=>now(),'updated_at'=>now()]);
            $v=(int)DB::connection('exam')->table('non_cadre_allocation_runs')->max('version')+1;
            $id=DB::connection('exam')->table('non_cadre_allocation_runs')->insertGetId([
                'version'=>$v,'cadre_allocation_a5_run_id'=>$a5->id,'cadre_allocation_candidate_hash'=>$a5->candidate_result_hash,
                'circular_version_id'=>$p['circular']->id,'seat_breakup_version_id'=>$p['seat']->id,'choice_import_id'=>$p['choice']->id,
                'status'=>'queued','phase'=>'INPUT_FREEZE','queue_stage'=>'INPUT_FREEZE','progress_percent'=>0,
                'input_hash'=>$this->sourceHash($p,$a5),'started_by'=>$actor,'queued_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            $this->audit('input_freeze_queued',$id,null,[], $actor);
            return $this->run($id);
        });
    }

    /** NC4.1 worker: immutable input snapshot. */
    public function processFreeze(int $runId,?int $actor): object
    {
        $run=$this->run($runId); if($run->is_stale||$run->status!=='queued'||$run->queue_stage!=='INPUT_FREEZE') throw ValidationException::withMessages(['allocation'=>'NC4 Input Freeze is not queued/current.']);
        $p=$this->requirePrerequisites(); $a5=AllocationA5Run::query()->whereKey($run->cadre_allocation_a5_run_id)->where('status','finalized')->where('is_stale',false)->firstOrFail();
        if((int)$run->circular_version_id!==(int)$p['circular']->id||(int)$run->seat_breakup_version_id!==(int)$p['seat']->id||(int)$run->choice_import_id!==(int)$p['choice']->id||!hash_equals((string)$run->input_hash,$this->sourceHash($p,$a5))) throw ValidationException::withMessages(['allocation'=>'NC4 upstream authority changed before Input Freeze worker started. Start a new freeze.']);
        $this->markProcessing($runId,'INPUT_FREEZE');
        DB::connection('exam')->table('non_cadre_allocation_input_candidates')->where('allocation_run_id',$runId)->delete();
        $this->materializeInput($runId,$p,$a5); $hash=$this->inputHash($runId);
        DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>'frozen','phase'=>'INPUT_FREEZE','queue_stage'=>null,'progress_percent'=>100,'freeze_hash'=>$hash,'total_candidates'=>DB::connection('exam')->table('non_cadre_allocation_input_candidates')->where('allocation_run_id',$runId)->count(),'processing_finished_at'=>now(),'updated_at'=>now()]);
        $this->audit('input_frozen',$runId,null,['freeze_hash'=>$hash],$actor); return $this->run($runId);
    }

    /** NC4.2: MQ + CFF/EM/PHC. Same higher-choice quota philosophy as Cadre A3. */
    public function phase1(int $runId,?int $actor): object
    {
        $run=$this->stageRun($runId,['INPUT_FREEZE']); $this->assertFreeze($run); $seats=$this->seatMap($run); $rows=[];
        foreach($this->inputs($runId) as $c){[$code,$basis,$pos]=$this->pickPhase1($c,$seats); if($code)$seats[$code][$basis]--; $rows[]=$this->phaseRow($runId,$c,$code,$basis,$pos,null,$code?'Phase-1 MQ/quota allocation.':'No Phase-1 seat available.');}
        $this->replaceRows('non_cadre_allocation_phase1_results',$runId,$rows); $h=$this->rowsHash('non_cadre_allocation_phase1_results',$runId);
        DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>'phase1_complete','phase'=>'PHASE1','queue_stage'=>null,'progress_percent'=>100,'processing_finished_at'=>now(),'phase1_hash'=>$h,'updated_at'=>now()]); $this->audit('phase1_completed',$runId,null,['phase1_hash'=>$h],$actor); return $this->run($runId);
    }

    /** NC4.3: vacant/released quota becomes pure merit capacity; global merit shifting/NM until fixed point. */
    public function phase2(int $runId,?int $actor): object
    {
        $run=$this->stageRun($runId,['PHASE1']); $this->assertFreeze($run); if(!hash_equals((string)$run->phase1_hash,$this->rowsHash('non_cadre_allocation_phase1_results',$runId)))throw ValidationException::withMessages(['allocation'=>'NC4 Phase-1 evidence changed.']);
        $inputs=$this->inputs($runId); $phase1=DB::connection('exam')->table('non_cadre_allocation_phase1_results')->where('allocation_run_id',$runId)->get()->keyBy('registration_id'); $seatBase=$this->seatMap($run); $assign=[]; foreach($phase1 as $r)if($r->post_code)$assign[(int)$r->registration_id]=$r;
        $iterations=0; do{$iterations++; $before=$this->assignmentSignature($assign); $converted=$this->convertedQuota($seatBase,$assign); $generic=[]; foreach($seatBase as $code=>$s)$generic[$code]=$s['MQ']+$converted[$code]; $next=[];
            foreach($inputs as $c){$old=$assign[(int)$c->registration_id]??null; $choices=$this->choices($c); $limit=$old?(int)$old->choice_position:count($choices); $picked=null;$pos=null; foreach($choices as $i=>$code){$p=$i+1;if($p>$limit)break;if(($generic[$code]??0)>0){$picked=$code;$pos=$p;$generic[$code]--;break;}}
                if($picked){$movement=!$old?'NM':(($old->post_code===$picked&&in_array($old->allocation_basis,['CFF','EM','PHC'],true))?'QUOTA_TO_MERIT':(($old->post_code===$picked)?null:'SHIFTED')); $next[(int)$c->registration_id]=(object)$this->phaseRow($runId,$c,$picked,'MQ',$pos,$movement,$movement==='SHIFTED'?'Higher-choice merit shifting.':($movement==='QUOTA_TO_MERIT'?'Quota seat normalized to merit/NM.':'Merit/NM allocation.'));}
                elseif($old)$next[(int)$c->registration_id]=$old;
            } $assign=$next; if($iterations>max(20,$inputs->count()+20))throw ValidationException::withMessages(['allocation'=>'NC4 Phase-2 convergence guard exceeded.']);
        }while($before!==$this->assignmentSignature($assign));
        $rows=[];$nm=$shift=$q2m=0; foreach($inputs as $c){$a=$assign[(int)$c->registration_id]??null;if($a){$x=(array)$a;$x['allocation_run_id']=$runId;$x['created_at']=now();$x['updated_at']=now();$rows[]=$x;if(($x['movement_type']??null)==='NM')$nm++;if(($x['movement_type']??null)==='SHIFTED')$shift++;if(($x['movement_type']??null)==='QUOTA_TO_MERIT')$q2m++;}else $rows[]=$this->phaseRow($runId,$c,null,null,null,null,'No seat available after NM/Shifting.');}
        $this->replaceRows('non_cadre_allocation_phase2_results',$runId,$rows); $this->syncSpecialReviews($runId,$run,$rows); $h=$this->rowsHash('non_cadre_allocation_phase2_results',$runId); DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>DB::connection('exam')->table('non_cadre_special_requirement_reviews')->where('allocation_run_id',$runId)->where('decision','PENDING')->exists()?'special_review':'phase2_complete','phase'=>'PHASE2','queue_stage'=>null,'progress_percent'=>100,'processing_finished_at'=>now(),'phase2_hash'=>$h,'nm_count'=>$nm,'shifted_count'=>$shift,'quota_to_merit_count'=>$q2m,'updated_at'=>now()]); $this->audit('phase2_completed',$runId,null,['phase2_hash'=>$h,'iterations'=>$iterations,'nm'=>$nm,'shifted'=>$shift,'quota_to_merit'=>$q2m],$actor); return $this->run($runId);
    }

    /** NC4.4: fail-closed integrity checks, then materialize final result only on PASS. */
    public function validate(int $runId,?int $actor): object
    {
        $run=$this->stageRun($runId,['PHASE2']); if(DB::connection('exam')->table('non_cadre_special_requirement_reviews')->where('allocation_run_id',$runId)->where('decision','PENDING')->exists())throw ValidationException::withMessages(['allocation'=>'Resolve all Special Requirement reviews before NC4 Validation.']); $checks=[]; $add=function($code,$ok,$msg,$ctx=[])use(&$checks,$runId){$checks[]=['allocation_run_id'=>$runId,'check_code'=>$code,'status'=>$ok?'PASS':'FAIL','message'=>$msg,'context'=>$ctx?json_encode($ctx):null,'created_at'=>now(),'updated_at'=>now()];};
        $this->assertFreeze($run); $p2=DB::connection('exam')->table('non_cadre_allocation_phase2_results')->where('allocation_run_id',$runId)->get(); $allocated=$p2->whereNotNull('post_code');
        $add('UNIQUE_CANDIDATE',$allocated->pluck('registration_id')->duplicates()->isEmpty(),'One allocation maximum per candidate.');
        $seat=$this->seatMap($run); $over=[]; foreach($allocated->groupBy('post_code') as $code=>$g)if($g->count()>array_sum($seat[$code]??[]))$over[$code]=[$g->count(),array_sum($seat[$code]??[])]; $add('SEAT_CONSERVATION',$over===[],'No post exceeds finalized NC2 capacity.',$over);
        $a5=AllocationA5Run::query()->whereKey($run->cadre_allocation_a5_run_id)->where('status','finalized')->where('is_stale',false)->first(); $cadre=[]; if($a5)$cadre=AllocationA5CandidateResult::query()->where('allocation_a5_run_id',$a5->id)->where('overall_status','PASS')->pluck('registration_id')->map(fn($x)=>(int)$x)->all(); $conf=$allocated->whereIn('registration_id',$cadre)->pluck('reg')->all(); $add('NO_CADRE_ALLOCATED_CANDIDATE',$a5&&hash_equals((string)$run->cadre_allocation_candidate_hash,(string)$a5->candidate_result_hash)&&$conf===[],'Cadre authority is current and no Cadre-allocated candidate leaked into NC4.',$conf);

        $inputByCandidate=DB::connection('exam')->table('non_cadre_allocation_input_candidates')->where('allocation_run_id',$runId)->get()->keyBy('registration_id');
        $choiceBad=[]; foreach($allocated as $r){$i=$inputByCandidate->get($r->registration_id);$ch=$i?$this->choices($i):[];if(($ch[(int)$r->choice_position-1]??null)!==$r->post_code)$choiceBad[]=['reg'=>$r->reg,'post_code'=>$r->post_code,'choice_position'=>$r->choice_position];} $add('ALLOCATION_READY_CHOICE',$choiceBad===[],'Every allocated post code is present at the recorded position in the frozen Allocation Ready Choice list.',$choiceBad);

        $registrations=DB::connection('exam')->table('registrations')->whereIn('id',$allocated->pluck('registration_id')->all())->get(['id','reg','bachelor_subject_code'])->keyBy('id');
        $posts=DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$run->circular_version_id)->whereIn('post_code',$allocated->pluck('post_code')->filter()->unique()->values()->all())->get(['post_code','bachelor_subject_codes'])->keyBy('post_code');
        $subjectBad=[]; foreach($allocated as $r){$registration=$registrations->get($r->registration_id);$post=$posts->get($r->post_code);$allowed=$post?array_values(array_filter(array_map('trim',explode('|',(string)($post->bachelor_subject_codes??''))))):[];$subject=trim((string)($registration->bachelor_subject_code??''));if(!$post||($allowed!==[]&&!in_array($subject,$allowed,true)))$subjectBad[]=['reg'=>$r->reg,'post_code'=>$r->post_code,'bachelor_subject_code'=>$subject?:null,'allowed_subject_codes'=>$allowed];} $add('BACHELOR_SUBJECT_ELIGIBILITY',$subjectBad===[],'Every allocated candidate satisfies the finalized Circular Bachelor Subject eligibility for the allocated post.',$subjectBad);

        $finalByCandidate=$p2->keyBy('registration_id'); $meritBad=[];
        foreach($allocated->where('allocation_basis','MQ') as $seatHolder){
            foreach($inputByCandidate as $higher){
                if((int)$higher->common_merit_position >= (int)$seatHolder->common_merit_position) continue;
                $higherChoices=$this->choices($higher); $targetIndex=array_search($seatHolder->post_code,$higherChoices,true); if($targetIndex===false) continue;
                $higherResult=$finalByCandidate->get($higher->registration_id); $higherFinalPosition=$higherResult?->post_code?(int)$higherResult->choice_position:PHP_INT_MAX;
                if($higherFinalPosition>$targetIndex+1){$meritBad[]=['higher_merit_reg'=>$higher->reg,'higher_merit_position'=>(int)$higher->common_merit_position,'post_code'=>$seatHolder->post_code,'post_choice_position'=>$targetIndex+1,'lower_merit_reg'=>$seatHolder->reg,'lower_merit_position'=>(int)$seatHolder->common_merit_position];break;}
            }
        }
        $add('COMMON_MERIT_INTEGRITY',$meritBad===[],'No MQ/NM seat is held by a lower Common Merit candidate while a higher Common Merit candidate listed that post and remained unallocated or at a lower preference.',$meritBad);
        $fail=collect($checks)->contains(fn($x)=>$x['status']==='FAIL'); DB::connection('exam')->table('non_cadre_allocation_validation_checks')->where('allocation_run_id',$runId)->delete(); DB::connection('exam')->table('non_cadre_allocation_validation_checks')->insert($checks); $vh=hash('sha256',json_encode($checks));
        DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>$fail?'validation_failed':'validated','phase'=>'VALIDATION','queue_stage'=>null,'progress_percent'=>100,'processing_finished_at'=>now(),'validation_hash'=>$vh,'updated_at'=>now()]); $this->audit('validation_completed',$runId,null,['status'=>$fail?'FAIL':'PASS','validation_hash'=>$vh],$actor); return $this->run($runId);
    }

    public function review(int $runId,int $reviewId,string $decision,string $reason,?int $actor): object
    {
        $decision=strtoupper($decision); if(!in_array($decision,['APPROVED','REJECTED'],true)||trim($reason)==='')throw ValidationException::withMessages(['review'=>'APPROVED/REJECTED and a reason are required.']);
        $r=DB::connection('exam')->table('non_cadre_special_requirement_reviews')->where('allocation_run_id',$runId)->find($reviewId); if(!$r)abort(404);
        DB::connection('exam')->table('non_cadre_special_requirement_reviews')->where('id',$reviewId)->update(['decision'=>$decision,'reason'=>trim($reason),'reviewed_by'=>$actor,'reviewed_at'=>now(),'updated_at'=>now()]);
        $this->audit('special_requirement_'.strtolower($decision),$reviewId,null,['decision'=>$decision,'reason'=>trim($reason)],$actor); return $this->run($runId);
    }

    public function reviews(int $id): Collection
    {
        $rows = DB::connection('exam')->table('non_cadre_special_requirement_reviews as s')
            ->leftJoin('registrations as g', 'g.id', '=', 's.registration_id')
            ->leftJoin('non_cadre_circular_posts as p', 'p.id', '=', 's.circular_post_id')
            ->where('s.allocation_run_id', $id)
            ->select('s.*', 'g.name', 'g.sex_code', 'g.bachelor_subject_code', 'p.post_title', 'p.special_requirement_note')
            ->orderByRaw("FIELD(s.decision,'PENDING','REJECTED','APPROVED')")
            ->orderBy('s.reg')
            ->get();

        $genderNames = Gender::query()->whereIn('code', $rows->pluck('sex_code')->filter()->unique())->pluck('name', 'code');
        $subjectNames = BachelorSubject::query()->whereIn('subject_code', $rows->pluck('bachelor_subject_code')->filter()->unique())->pluck('subject_name', 'subject_code');

        return $rows->each(function ($row) use ($genderNames, $subjectNames): void {
            $row->sex_label = filled($row->sex_code)
                ? ((string) $row->sex_code).' - '.((string) ($genderNames->get($row->sex_code) ?: 'Unmapped'))
                : '—';
            $row->bachelor_subject_label = filled($row->bachelor_subject_code)
                ? ((string) $row->bachelor_subject_code).' - '.((string) ($subjectNames->get($row->bachelor_subject_code) ?: 'Unmapped'))
                : '—';
        });
    }

    public function finalize(int $runId,?int $actor): object
    {
        $run=$this->stageRun($runId,['VALIDATION']); if($run->status!=='validated')throw ValidationException::withMessages(['allocation'=>'NC4 Validation must PASS before finalization.']); $this->assertFreeze($run);
        $required=['UNIQUE_CANDIDATE','SEAT_CONSERVATION','NO_CADRE_ALLOCATED_CANDIDATE','ALLOCATION_READY_CHOICE','BACHELOR_SUBJECT_ELIGIBILITY','COMMON_MERIT_INTEGRITY'];
        $passed=DB::connection('exam')->table('non_cadre_allocation_validation_checks')->where('allocation_run_id',$runId)->where('status','PASS')->whereIn('check_code',$required)->pluck('check_code')->all();
        $missing=array_values(array_diff($required,$passed));
        if($missing!==[]||DB::connection('exam')->table('non_cadre_allocation_validation_checks')->where('allocation_run_id',$runId)->where('status','FAIL')->exists())throw ValidationException::withMessages(['allocation'=>'NC4 critical validation whitelist is incomplete or contains FAIL: '.implode(', ',$missing)]);
        return DB::connection('exam')->transaction(function()use($run,$runId,$actor){$p2=DB::connection('exam')->table('non_cadre_allocation_phase2_results')->where('allocation_run_id',$runId)->get(); DB::connection('exam')->table('non_cadre_allocation_results')->where('allocation_run_id',$runId)->delete(); $rows=[]; foreach($p2 as $r)$rows[]=['allocation_run_id'=>$runId,'registration_id'=>$r->registration_id,'reg'=>$r->reg,'common_merit_position'=>$r->common_merit_position,'circular_post_id'=>$r->circular_post_id,'post_code'=>$r->post_code,'choice_position'=>$r->choice_position,'allocation_basis'=>$r->allocation_basis,'decision_status'=>$r->post_code?'FINAL':'UNALLOCATED','decision_reason'=>$r->decision_reason,'requires_special_review'=>false,'created_at'=>now(),'updated_at'=>now()]; if($rows)DB::connection('exam')->table('non_cadre_allocation_results')->insert($rows); $h=$this->resultHash($runId);$a=collect($rows)->whereNotNull('post_code')->count();DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>'finalized','phase'=>'FINALIZED','result_hash'=>$h,'total_allocated'=>$a,'total_unallocated'=>count($rows)-$a,'finalized_by'=>$actor,'finalized_at'=>now(),'updated_at'=>now()]);DB::connection('exam')->table('non_cadre_processing_states')->where('id',1)->update(['allocation_status'=>'finalized','reporting_status'=>'not_started','updated_at'=>now()]);$this->audit('allocation_finalized',$runId,null,['result_hash'=>$h],$actor);return $this->run($runId);});
    }

    public function queueStage(int $runId,string $stage): object
    {
        $allowed=['PHASE1'=>['INPUT_FREEZE'], 'PHASE2'=>['PHASE1'], 'VALIDATION'=>['PHASE2'], 'RECOMPUTE'=>['PHASE2']];
        if(!isset($allowed[$stage])) throw ValidationException::withMessages(['allocation'=>'Unknown NC4 queue stage.']);
        $run=$this->stageRun($runId,$allowed[$stage]);
        if(in_array((string)$run->status,['queued','processing'],true)) throw ValidationException::withMessages(['allocation'=>'An NC4 stage is already queued/processing.']);
        DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>'queued','queue_stage'=>$stage,'progress_percent'=>0,'failure_message'=>null,'queued_at'=>now(),'processing_started_at'=>null,'processing_finished_at'=>null,'updated_at'=>now()]);
        return $this->run($runId);
    }

    public function processQueuedStage(int $runId,string $stage,?int $actor): object
    {
        $run=$this->run($runId); if($run->is_stale||$run->status!=='queued'||$run->queue_stage!==$stage) throw ValidationException::withMessages(['allocation'=>'NC4 queued stage/currentness check failed.']);
        if($stage!=='INPUT_FREEZE') $this->markProcessing($runId,$stage);
        return match($stage){'INPUT_FREEZE'=>$this->processFreeze($runId,$actor),'PHASE1'=>$this->phase1($runId,$actor),'PHASE2'=>$this->phase2($runId,$actor),'VALIDATION'=>$this->validate($runId,$actor),'RECOMPUTE'=>$this->recompute($runId,$actor),default=>throw ValidationException::withMessages(['allocation'=>'Unknown NC4 queue stage.'])};
    }

    private function recompute(int $runId,?int $actor): object
    {
        DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['phase'=>'INPUT_FREEZE','status'=>'processing','queue_stage'=>'RECOMPUTE','progress_percent'=>10,'updated_at'=>now()]);
        $this->phase1($runId,$actor);
        DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>'processing','queue_stage'=>'RECOMPUTE','progress_percent'=>55,'updated_at'=>now()]);
        $out=$this->phase2($runId,$actor);
        return $out;
    }

    public function markStageFailed(int $runId,string $stage,string $message): void
    {
        DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>'failed','queue_stage'=>$stage,'failure_message'=>mb_substr($message,0,4000),'processing_finished_at'=>now(),'updated_at'=>now()]);
    }

    public function progress(int $runId): array
    {
        $r=$this->run($runId); return ['id'=>$r->id,'version'=>$r->version,'phase'=>$r->phase,'status'=>$r->status,'queue_stage'=>$r->queue_stage,'progress_percent'=>(int)($r->progress_percent??0),'failure_message'=>$r->failure_message??null];
    }

    private function markProcessing(int $runId,string $stage): void { DB::connection('exam')->table('non_cadre_allocation_runs')->where('id',$runId)->update(['status'=>'processing','queue_stage'=>$stage,'progress_percent'=>5,'failure_message'=>null,'processing_started_at'=>now(),'updated_at'=>now()]); }

    public function run(int $id):object{$r=DB::connection('exam')->table('non_cadre_allocation_runs')->find($id);abort_if(!$r,404);return $r;}

    public function runs():Collection
    {
        return DB::connection('exam')->table('non_cadre_allocation_runs')->orderByDesc('version')->get()->each(function($run): void {
            $counts=$this->basisCounts((int)$run->id);
            $run->quota_allocated_count=$counts['quota'];
            $run->cff_allocated_count=$counts['CFF'];
            $run->em_allocated_count=$counts['EM'];
            $run->phc_allocated_count=$counts['PHC'];
        });
    }

    public function results(int $id,array $filters=[]):LengthAwarePaginator
    {
        $table=$this->resultTable($this->run($id));
        $q=DB::connection('exam')->table("$table as r")->leftJoin('registrations as g','g.id','=','r.registration_id')->where('r.allocation_run_id',$id)->select('r.*','g.user_id','g.name');
        if($search=trim((string)($filters['search']??'')))$q->where(function($x)use($search){$x->where('r.reg','like',"%{$search}%")->orWhere('g.user_id','like',"%{$search}%")->orWhere('g.name','like',"%{$search}%");});
        if($post=trim((string)($filters['post_code']??'')))$q->where('r.post_code',$post);
        if($basis=trim((string)($filters['basis']??'')))$q->where('r.allocation_basis',$basis);
        if($movement=trim((string)($filters['movement']??'')))$q->where('r.movement_type',$movement);
        if(($filters['allocation']??'')==='allocated')$q->whereNotNull('r.post_code');
        if(($filters['allocation']??'')==='unallocated')$q->whereNull('r.post_code');
        return $q->orderBy('r.common_merit_position')->paginate(20)->withQueryString();
    }

    public function allocationSummary(int $id):array
    {
        $run=$this->run($id);$counts=$this->basisCounts($id);
        $table=$this->resultTable($run);
        $allocated=DB::connection('exam')->table($table)->where('allocation_run_id',$id)->whereNotNull('post_code')->count();
        return ['allocated'=>$allocated,'quota'=>$counts['quota'],'MQ'=>$counts['MQ'],'CFF'=>$counts['CFF'],'EM'=>$counts['EM'],'PHC'=>$counts['PHC']];
    }

    public function postWiseSummary(int $id):Collection
    {
        $run=$this->run($id);$table=$this->resultTable($run);
        $allocated=DB::connection('exam')->table($table)->where('allocation_run_id',$id)->whereNotNull('post_code')->selectRaw("post_code, COUNT(*) allocated, SUM(allocation_basis='MQ') mq_allocated, SUM(allocation_basis='CFF') cff_allocated, SUM(allocation_basis='EM') em_allocated, SUM(allocation_basis='PHC') phc_allocated")->groupBy('post_code')->get()->keyBy('post_code');
        return DB::connection('exam')->table('non_cadre_circular_posts as p')->leftJoin('non_cadre_seat_breakup_rows as s',function($j)use($run){$j->on('s.post_code','=','p.post_code')->where('s.seat_breakup_version_id','=',$run->seat_breakup_version_id);})
            ->where('p.circular_version_id',$run->circular_version_id)->where('p.status','ACTIVE')
            ->select('p.post_grade','p.post_serial','p.post_sub_serial','p.post_code','p.post_title','p.post_count','s.mq_post','s.cff_post','s.em_post','s.phc_post')
            ->orderByRaw('CAST(p.post_grade AS UNSIGNED)')->orderByRaw('CAST(p.post_serial AS UNSIGNED)')->orderByRaw('CAST(COALESCE(p.post_sub_serial,0) AS UNSIGNED)')->get()->map(function($p)use($allocated){$a=$allocated->get($p->post_code);$p->allocated=(int)($a->allocated??0);$p->mq_allocated=(int)($a->mq_allocated??0);$p->cff_allocated=(int)($a->cff_allocated??0);$p->em_allocated=(int)($a->em_allocated??0);$p->phc_allocated=(int)($a->phc_allocated??0);$p->vacant=max(0,(int)$p->post_count-$p->allocated);return$p;});
    }

    public function statusBoard(?object $run,array $prerequisites):array
    {
        $done=fn(string $phase)=>$run && in_array($run->phase,[$phase,'PHASE1','PHASE2','VALIDATION','FINALIZED'],true);
        if(!$run)return [['label'=>'Readiness','status'=>$prerequisites['ready']?'READY':'BLOCKED'],['label'=>'Input Freeze','status'=>'NOT STARTED'],['label'=>'Phase-1','status'=>'WAITING'],['label'=>'Phase-2','status'=>'WAITING'],['label'=>'Validation','status'=>'WAITING'],['label'=>'Finalization','status'=>'WAITING']];
        return [
            ['label'=>'Readiness','status'=>$prerequisites['ready']?'READY':'BLOCKED'],
            ['label'=>'Input Freeze','status'=>$done('INPUT_FREEZE')?'COMPLETED':strtoupper((string)$run->status)],
            ['label'=>'Phase-1','status'=>in_array($run->phase,['PHASE1','PHASE2','VALIDATION','FINALIZED'],true)?'COMPLETED':(($run->queue_stage??null)==='PHASE1'?strtoupper($run->status):'WAITING')],
            ['label'=>'Phase-2','status'=>in_array($run->phase,['PHASE2','VALIDATION','FINALIZED'],true)?'COMPLETED':(($run->queue_stage??null)==='PHASE2'?strtoupper($run->status):'WAITING')],
            ['label'=>'Validation','status'=>in_array($run->phase,['VALIDATION','FINALIZED'],true)?($run->status==='failed'?'FAILED':'COMPLETED'):(($run->queue_stage??null)==='VALIDATION'?strtoupper($run->status):'WAITING')],
            ['label'=>'Finalization','status'=>$run->phase==='FINALIZED'?'FINALIZED':'WAITING'],
        ];
    }

    public function checks(int $id):Collection{return DB::connection('exam')->table('non_cadre_allocation_validation_checks')->where('allocation_run_id',$id)->orderBy('id')->get();}

    private function resultTable(object $run):string{return $run->phase==='FINALIZED'?'non_cadre_allocation_results':($run->phase==='PHASE1'?'non_cadre_allocation_phase1_results':'non_cadre_allocation_phase2_results');}
    private function basisCounts(int $id):array{$run=$this->run($id);$table=$this->resultTable($run);$rows=DB::connection('exam')->table($table)->where('allocation_run_id',$id)->whereNotNull('post_code')->selectRaw('allocation_basis, COUNT(*) c')->groupBy('allocation_basis')->pluck('c','allocation_basis');$out=['MQ'=>(int)($rows['MQ']??0),'CFF'=>(int)($rows['CFF']??0),'EM'=>(int)($rows['EM']??0),'PHC'=>(int)($rows['PHC']??0)];$out['quota']=$out['CFF']+$out['EM']+$out['PHC'];return$out;}

    private function materializeInput(int $id,array $p,AllocationA5Run $a5):void{$m=$this->merit->verifiedSummary();$cadre=AllocationA5CandidateResult::query()->where('allocation_a5_run_id',$a5->id)->where('overall_status','PASS')->pluck('registration_id')->map(fn($x)=>(int)$x)->flip();$items=DB::connection('exam')->table('non_cadre_choice_items as i')->join('merit_results as m','m.registration_id','=','i.registration_id')->join('registrations as g','g.id','=','i.registration_id')->where('i.choice_import_id',$p['choice']->id)->where('i.validation_status','valid')->where('m.processing_run_id',(int)$m['processing_run_id'])->whereNotNull('m.common_merit_position')->select('i.*','m.common_merit_position','g.has_ff_quota','g.has_em_quota','g.has_phc_quota')->orderBy('m.common_merit_position')->get()->unique('registration_id');$rows=[];foreach($items as $x){$sum=json_decode((string)$x->validation_summary,true)?:[];if(($sum['population_status']??null)!=='ALLOCATION_ELIGIBLE'||isset($cadre[(int)$x->registration_id]))continue;$ch=array_values(array_filter(json_decode((string)$x->effective_choices,true)?:[],fn($v)=>trim((string)$v)!==''));if(!$ch)continue;$rows[]=['allocation_run_id'=>$id,'registration_id'=>$x->registration_id,'reg'=>$x->reg,'common_merit_position'=>$x->common_merit_position,'has_cff'=>(bool)$x->has_ff_quota,'has_em'=>(bool)$x->has_em_quota,'has_phc'=>(bool)$x->has_phc_quota,'allocation_ready_choices'=>json_encode($ch),'created_at'=>now(),'updated_at'=>now()];}if($rows)DB::connection('exam')->table('non_cadre_allocation_input_candidates')->insert($rows);}
    private function pickPhase1(object $c,array $seats):array{$ch=$this->choices($c);$mq=null;foreach($ch as $i=>$code)if(($seats[$code]['MQ']??0)>0){$mq=$i;break;}$limit=$mq===null?count($ch):$mq;foreach($ch as $i=>$code){if($i>=$limit)break;foreach(['CFF'=>'has_cff','EM'=>'has_em','PHC'=>'has_phc'] as $q=>$f)if($c->$f&&($seats[$code][$q]??0)>0)return[$code,$q,$i+1];}if($mq!==null)return[$ch[$mq],'MQ',$mq+1];foreach($ch as $i=>$code)foreach(['CFF'=>'has_cff','EM'=>'has_em','PHC'=>'has_phc'] as $q=>$f)if($c->$f&&($seats[$code][$q]??0)>0)return[$code,$q,$i+1];return[null,null,null];}
    private function convertedQuota(array $seat,array $assign):array{$used=[];foreach($assign as $a)if(in_array($a->allocation_basis,['CFF','EM','PHC'],true))$used[$a->post_code][$a->allocation_basis]=($used[$a->post_code][$a->allocation_basis]??0)+1;$out=[];foreach($seat as $code=>$s)$out[$code]=max(0,$s['CFF']-($used[$code]['CFF']??0))+max(0,$s['EM']-($used[$code]['EM']??0))+max(0,$s['PHC']-($used[$code]['PHC']??0));return$out;}
    private function seatMap(object $r):array{$rows=DB::connection('exam')->table('non_cadre_seat_breakup_rows')->where('seat_breakup_version_id',$r->seat_breakup_version_id)->get();$x=[];foreach($rows as $s)$x[$s->post_code]=['MQ'=>(int)$s->mq_post,'CFF'=>(int)$s->cff_post,'EM'=>(int)$s->em_post,'PHC'=>(int)$s->phc_post];return$x;}
    private function inputs(int $id):Collection{return DB::connection('exam')->table('non_cadre_allocation_input_candidates')->where('allocation_run_id',$id)->orderBy('common_merit_position')->orderBy('registration_id')->get();}
    private function choices(object $c):array{$x=array_values(json_decode((string)$c->allocation_ready_choices,true)?:[]);$rejected=DB::connection('exam')->table('non_cadre_special_requirement_reviews')->where('allocation_run_id',$c->allocation_run_id)->where('registration_id',$c->registration_id)->where('decision','REJECTED')->pluck('post_code')->all();return array_values(array_filter($x,fn($code)=>!in_array($code,$rejected,true)));}
    private function syncSpecialReviews(int $runId,object $run,array $rows):void{$posts=DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$run->circular_version_id)->where('special_requirement',true)->get()->keyBy('post_code');$needed=[];foreach($rows as $r){if(!$r['post_code']||!isset($posts[$r['post_code']]))continue;$key=$r['registration_id'].'|'.$r['post_code'];$needed[$key]=true;$exists=DB::connection('exam')->table('non_cadre_special_requirement_reviews')->where('allocation_run_id',$runId)->where('registration_id',$r['registration_id'])->where('post_code',$r['post_code'])->exists();if(!$exists)DB::connection('exam')->table('non_cadre_special_requirement_reviews')->insert(['allocation_run_id'=>$runId,'registration_id'=>$r['registration_id'],'reg'=>$r['reg'],'circular_post_id'=>$posts[$r['post_code']]->id,'post_code'=>$r['post_code'],'decision'=>'PENDING','created_at'=>now(),'updated_at'=>now()]);}DB::connection('exam')->table('non_cadre_special_requirement_reviews')->where('allocation_run_id',$runId)->where('decision','PENDING')->get()->each(function($r)use($needed){if(!isset($needed[$r->registration_id.'|'.$r->post_code]))DB::connection('exam')->table('non_cadre_special_requirement_reviews')->where('id',$r->id)->delete();});}
    private function phaseRow(int $id,object $c,?string $code,?string $basis,?int $pos,?string $movement,string $reason):array{$post=$code?DB::connection('exam')->table('non_cadre_circular_posts')->where('post_code',$code)->first():null;return['allocation_run_id'=>$id,'registration_id'=>$c->registration_id,'reg'=>$c->reg,'common_merit_position'=>$c->common_merit_position,'circular_post_id'=>$post?->id,'post_code'=>$code,'choice_position'=>$pos,'allocation_basis'=>$basis,'movement_type'=>$movement,'decision_reason'=>$reason,'created_at'=>now(),'updated_at'=>now()];}
    private function replaceRows(string $t,int $id,array $r):void
    {
        DB::connection('exam')->table($t)->where('allocation_run_id',$id)->delete(); if(!$r)return;
        if(in_array($t,['non_cadre_allocation_phase1_results','non_cadre_allocation_phase2_results'],true)){
            $keys=['allocation_run_id','registration_id','reg','common_merit_position','circular_post_id','post_code','choice_position','allocation_basis','movement_type','decision_reason','created_at','updated_at'];
            $r=array_map(static function(array $row)use($keys){$out=[];foreach($keys as $key)$out[$key]=$row[$key]??null;return$out;},$r);
        }
        foreach(array_chunk($r,500) as $chunk) DB::connection('exam')->table($t)->insert($chunk);
    }
    private function rowsHash(string $t,int $id):string{return hash('sha256',json_encode(DB::connection('exam')->table($t)->where('allocation_run_id',$id)->orderBy('common_merit_position')->get()->map(fn($r)=>[$r->registration_id,$r->post_code,$r->choice_position,$r->allocation_basis,$r->movement_type])->all()));}
    private function assignmentSignature(array $a):string{ksort($a);return hash('sha256',json_encode(array_map(fn($r)=>[$r->post_code,$r->choice_position,$r->allocation_basis],$a)));}
    private function inputHash(int $id):string{return hash('sha256',json_encode($this->inputs($id)->map(fn($r)=>[$r->registration_id,$r->common_merit_position,$r->has_cff,$r->has_em,$r->has_phc,$r->allocation_ready_choices])->all()));}
    private function assertFreeze(object $r):void{if(!hash_equals((string)$r->freeze_hash,$this->inputHash($r->id)))throw ValidationException::withMessages(['allocation'=>'NC4 frozen input hash mismatch. Re-freeze inputs.']);}
    private function stageRun(int $id,array $phases):object{$r=$this->run($id);if($r->is_stale||$r->status==='finalized'||!in_array($r->phase,$phases,true))throw ValidationException::withMessages(['allocation'=>'NC4 stage order/currentness check failed.']);return$r;}
    private function current(string $t):?object{return DB::connection('exam')->table($t)->where('status','finalized')->where('is_stale',false)->orderByDesc('version')->first();}
    private function requirePrerequisites():array{$p=$this->prerequisites();if(!$p['ready'])throw ValidationException::withMessages(['allocation'=>$p['reason']]);return$p;}
    private function sourceHash(array $p,AllocationA5Run $a5):string{return hash('sha256',implode('|',[$a5->candidate_result_hash,$p['circular']->dataset_hash,$p['seat']->dataset_hash,$p['choice']->dataset_hash]));}
    private function resultHash(int $id):string{return hash('sha256',json_encode(DB::connection('exam')->table('non_cadre_allocation_results')->where('allocation_run_id',$id)->orderBy('common_merit_position')->get()->map(fn($r)=>[$r->registration_id,$r->post_code,$r->choice_position,$r->allocation_basis])->all()));}
    private function audit(string $a,int $id,?array $b,?array $n,?int $actor):void{DB::connection('exam')->table('non_cadre_processing_audits')->insert(['stage'=>'allocation','action'=>$a,'entity_type'=>'allocation','entity_id'=>$id,'before_payload'=>$b?json_encode($b):null,'after_payload'=>$n?json_encode($n):null,'actor_id'=>$actor,'created_at'=>now()]);}
}
