<?php

namespace App\Services\NonCadre\Reporting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class NonCadreReportingService
{
    public function gate(): array
    {
        $run = DB::connection('exam')->table('non_cadre_allocation_runs')
            ->where('status', 'finalized')->where('phase', 'FINALIZED')->where('is_stale', false)
            ->orderByDesc('version')->first();
        if (! $run) return ['ready'=>false,'reason'=>'Finalize a current NC4 — Non-Cadre Allocation run before opening NC5 Reporting.','run'=>null];
        if (! filled($run->result_hash)) return ['ready'=>false,'reason'=>'The finalized NC4 run has no result integrity hash.','run'=>$run];
        $hash = $this->resultHash((int)$run->id);
        if (! hash_equals((string)$run->result_hash, $hash)) return ['ready'=>false,'reason'=>'NC4 finalized result evidence changed. Reporting is blocked until Allocation is revalidated/finalized.','run'=>$run];
        return ['ready'=>true,'reason'=>null,'run'=>$run];
    }

    public function requireReady(): object
    {
        $gate=$this->gate();
        if(!($gate['ready']??false)) throw ValidationException::withMessages(['reporting'=>$gate['reason']]);
        return $gate['run'];
    }

    public function commonMerit(bool $identity, int $perPage=100): LengthAwarePaginator
    {
        $run=$this->requireReady();
        return DB::connection('exam')->table('non_cadre_allocation_input_candidates as i')
            ->leftJoin('non_cadre_allocation_results as a', function($j)use($run){$j->on('a.registration_id','=','i.registration_id')->where('a.allocation_run_id','=',$run->id);})
            ->leftJoin('registrations as r','r.id','=','i.registration_id')
            ->leftJoin('non_cadre_circular_posts as p','p.id','=','a.circular_post_id')
            ->where('i.allocation_run_id',$run->id)
            ->select(['i.registration_id','i.reg','i.common_merit_position','i.has_cff','i.has_em','i.has_phc','i.allocation_ready_choices','i.historical_excluded','i.historical_exclusion_reason','a.post_code','a.choice_position','a.allocation_basis','a.decision_status','a.decision_reason','p.post_title',
                ...($identity?['r.user_id','r.name','r.birth_date']:[])])
            ->orderBy('i.common_merit_position')->orderBy('i.registration_id')->paginate($perPage)->withQueryString();
    }

    public function posts(): Collection
    {
        $run=$this->requireReady();
        $allocated = DB::connection('exam')->table('non_cadre_allocation_results')
            ->selectRaw('post_code, COUNT(*) as allocated_post')
            ->where('allocation_run_id', $run->id)
            ->whereNotNull('post_code')
            ->groupBy('post_code');

        return DB::connection('exam')->table('non_cadre_circular_posts as p')
            ->leftJoinSub($allocated, 'a', fn ($join) => $join->on('a.post_code', '=', 'p.post_code'))
            ->where('p.circular_version_id', $run->circular_version_id)
            ->where('p.status', 'ACTIVE')
            ->select('p.*')
            ->selectRaw('COALESCE(a.allocated_post, 0) as allocated_post')
            ->orderByRaw('CAST(p.post_grade AS UNSIGNED)')
            ->orderByRaw('CAST(p.post_serial AS UNSIGNED)')
            ->orderByRaw('CAST(COALESCE(p.post_sub_serial,0) AS UNSIGNED)')
            ->get();
    }

    public function postReport(string $postCode,bool $identity,int $perPage=100): array
    {
        $run=$this->requireReady();
        $post=DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$run->circular_version_id)->where('post_code',$postCode)->where('status','ACTIVE')->first();
        abort_if(!$post,404);
        $rows=DB::connection('exam')->table('non_cadre_allocation_input_candidates as i')
            ->leftJoin('non_cadre_allocation_results as a',function($j)use($run){$j->on('a.registration_id','=','i.registration_id')->where('a.allocation_run_id','=',$run->id);})
            ->leftJoin('registrations as r','r.id','=','i.registration_id')
            ->where('i.allocation_run_id',$run->id)
            ->whereJsonContains('i.allocation_ready_choices',$postCode)
            ->select(['i.registration_id','i.reg','i.common_merit_position','i.has_cff','i.has_em','i.has_phc','i.allocation_ready_choices','i.historical_excluded','i.historical_exclusion_reason','a.post_code as allocated_post_code','a.choice_position as allocated_choice_position','a.allocation_basis','a.decision_status',
                ...($identity?['r.user_id','r.name','r.birth_date']:[])])
            ->orderBy('i.common_merit_position')->paginate($perPage)->withQueryString();
        $rows->getCollection()->transform(function($row)use($postCode){$choices=json_decode((string)$row->allocation_ready_choices,true)?:[];$pos=array_search($postCode,$choices,true);$row->report_choice_position=$pos===false?null:$pos+1;return$row;});
        $post->allocated_post = DB::connection('exam')->table('non_cadre_allocation_results')->where('allocation_run_id',$run->id)->where('post_code',$postCode)->count();
        return compact('run','post','rows');
    }

    public function postAllocatedReport(string $postCode,bool $identity,int $perPage=100): array
    {
        $run=$this->requireReady();
        $post=DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$run->circular_version_id)->where('post_code',$postCode)->where('status','ACTIVE')->first();
        abort_if(!$post,404);
        $rows=DB::connection('exam')->table('non_cadre_allocation_results as a')
            ->join('non_cadre_allocation_input_candidates as i',function($j){$j->on('i.registration_id','=','a.registration_id')->on('i.allocation_run_id','=','a.allocation_run_id');})
            ->leftJoin('registrations as r','r.id','=','a.registration_id')
            ->where('a.allocation_run_id',$run->id)->where('a.post_code',$postCode)
            ->select(['i.registration_id','i.reg','i.common_merit_position','i.has_cff','i.has_em','i.has_phc','i.allocation_ready_choices','i.historical_excluded','i.historical_exclusion_reason','a.post_code as allocated_post_code','a.choice_position as allocated_choice_position','a.allocation_basis','a.decision_status',
                ...($identity?['r.user_id','r.name','r.birth_date']:[])])
            ->orderBy('i.common_merit_position')->orderBy('i.registration_id')->paginate($perPage)->withQueryString();
        $rows->getCollection()->transform(function($row)use($postCode){$choices=json_decode((string)$row->allocation_ready_choices,true)?:[];$pos=array_search($postCode,$choices,true);$row->report_choice_position=$pos===false?null:$pos+1;return$row;});
        $post->allocated_post = DB::connection('exam')->table('non_cadre_allocation_results')->where('allocation_run_id',$run->id)->where('post_code',$postCode)->count();
        return compact('run','post','rows');
    }

    public function commonMeritAll(bool $identity): Collection
    {
        $run=$this->requireReady();
        return DB::connection('exam')->table('non_cadre_allocation_input_candidates as i')
            ->leftJoin('non_cadre_allocation_results as a', function($j)use($run){$j->on('a.registration_id','=','i.registration_id')->where('a.allocation_run_id','=',$run->id);})
            ->leftJoin('registrations as r','r.id','=','i.registration_id')
            ->leftJoin('non_cadre_circular_posts as p','p.id','=','a.circular_post_id')
            ->where('i.allocation_run_id',$run->id)
            ->select(['i.registration_id','i.reg','i.common_merit_position','i.has_cff','i.has_em','i.has_phc','i.allocation_ready_choices','i.historical_excluded','i.historical_exclusion_reason','a.post_code','a.choice_position','a.allocation_basis','a.decision_status','a.decision_reason','p.post_title',...($identity?['r.name','r.birth_date']:[])])
            ->orderBy('i.common_merit_position')->orderBy('i.registration_id')->get();
    }

    public function postReportAll(string $postCode,bool $identity,bool $allocatedOnly=false): array
    {
        $run=$this->requireReady();
        $post=DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$run->circular_version_id)->where('post_code',$postCode)->where('status','ACTIVE')->first();
        abort_if(!$post,404);
        $q=DB::connection('exam')->table('non_cadre_allocation_input_candidates as i')
            ->leftJoin('non_cadre_allocation_results as a',function($j)use($run){$j->on('a.registration_id','=','i.registration_id')->where('a.allocation_run_id','=',$run->id);})
            ->leftJoin('registrations as r','r.id','=','i.registration_id')
            ->where('i.allocation_run_id',$run->id);
        if($allocatedOnly){$q->where('a.post_code',$postCode);}else{$q->whereJsonContains('i.allocation_ready_choices',$postCode);}
        $rows=$q->select(['i.registration_id','i.reg','i.common_merit_position','i.has_cff','i.has_em','i.has_phc','i.allocation_ready_choices','i.historical_excluded','i.historical_exclusion_reason','a.post_code as allocated_post_code','a.choice_position as allocated_choice_position','a.allocation_basis','a.decision_status',...($identity?['r.name','r.birth_date']:[])])
            ->orderBy('i.common_merit_position')->orderBy('i.registration_id')->get();
        $rows->transform(function($row)use($postCode){$choices=json_decode((string)$row->allocation_ready_choices,true)?:[];$pos=array_search($postCode,$choices,true);$row->report_choice_position=$pos===false?null:$pos+1;return$row;});
        $post->allocated_post=DB::connection('exam')->table('non_cadre_allocation_results')->where('allocation_run_id',$run->id)->where('post_code',$postCode)->count();
        return compact('run','post','rows');
    }

    public function serialMeritReport(): array
    {
        $run=$this->requireReady();
        $posts=$this->posts()->keyBy(fn($p)=>(string)$p->post_code);
        $alloc=DB::connection('exam')->table('non_cadre_allocation_results as a')
            ->join('non_cadre_allocation_input_candidates as i',function($j){$j->on('i.registration_id','=','a.registration_id')->on('i.allocation_run_id','=','a.allocation_run_id');})
            ->where('a.allocation_run_id',$run->id)->whereNotNull('a.post_code')
            ->select('a.post_code','a.allocation_basis','i.common_merit_position','i.registration_id')
            ->orderBy('i.common_merit_position')->orderBy('i.registration_id')->get()->groupBy('post_code');
        $rowsPerGroup=25;
        $sections=$posts->map(function($post)use($alloc,$rowsPerGroup){
            $candidateRows=collect($alloc->get((string)$post->post_code,collect()))->values()->map(fn($r,$i)=>['serial'=>$i+1,'merit_position'=>(int)$r->common_merit_position,'allocation_basis'=>strtoupper((string)($r->allocation_basis?:'—'))]);
            $pages=$candidateRows->chunk($rowsPerGroup*3)->map(fn($pageRows)=>['groups'=>collect([0,1,2])->map(fn($i)=>collect($pageRows->chunk($rowsPerGroup)->values()->get($i,collect()))->values())->values()])->values();
            if($pages->isEmpty())$pages=collect([['groups'=>collect([collect(),collect(),collect()])]]);
            return ['post_code'=>(string)$post->post_code,'heading'=>(string)$post->post_code.' - '.(string)$post->post_title.' - '.(string)($post->entity?:'—').' - '.(string)($post->ministry?:'—'),'total_post'=>(int)$post->post_count,'total_allocated'=>$candidateRows->count(),'pages'=>$pages];
        })->filter(fn($section)=>$section['total_allocated']>0)->values();
        return ['title'=>'Post-wise Serial, Common Merit & Allocation Basis Verification Report','rows_per_group'=>$rowsPerGroup,'sections'=>$sections,'run'=>$run];
    }

    public function txtContent(object $run,int $perLine,string $title,string $examName): string
    {
        $perLine=max(1,min(20,$perLine));
        $posts=DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$run->circular_version_id)->where('status','ACTIVE')->orderByRaw('CAST(post_grade AS UNSIGNED)')->orderByRaw('CAST(post_serial AS UNSIGNED)')->orderByRaw('CAST(COALESCE(post_sub_serial,0) AS UNSIGNED)')->get();
        $allocated=DB::connection('exam')->table('non_cadre_allocation_results')->where('allocation_run_id',$run->id)->whereNotNull('post_code')->orderBy('common_merit_position')->get()->groupBy('post_code');
        $lines=[$examName,$title,'Generation Time: '.now()->format('d-m-Y h:i A'),'TOTAL ALLOCATED = '.str_pad((string)$allocated->flatten(1)->count(),4,'0',STR_PAD_LEFT),''];
        foreach($posts as $post){$lines[]='#'.$post->post_code.' - '.$post->post_title;$lines[]='---------';$regs=$allocated->get($post->post_code,collect())->pluck('reg')->values();foreach($regs->chunk($perLine) as $chunk)$lines[]=$chunk->implode('   ');$lines[]='TOTAL = '.$regs->count();$lines[]='';$lines[]='';}
        return implode("\r\n",$lines);
    }

    public function docxReplacements(object $run,string $examName,string $resultDate,int $perLine): array
    {
        $perLine=max(1,min(20,$perLine));$repl=['EXAM_NAME'=>$examName,'RESULT_DATE'=>$resultDate,'TOTAL_ALLOCATED'=>(string)$run->total_allocated];
        foreach($this->posts() as $post){$key=preg_replace('/[^A-Z0-9]+/','_',strtoupper((string)$post->post_code));$regs=DB::connection('exam')->table('non_cadre_allocation_results')->where('allocation_run_id',$run->id)->where('post_code',$post->post_code)->orderBy('common_merit_position')->pluck('reg');$repl['POST_'.$key]=$regs->chunk($perLine)->map(fn($c)=>$c->implode('   '))->implode("\n") ?: 'NO ALLOCATABLE CANDIDATE WAS LEFT FOR THIS POST';$repl['TOTAL_'.$key]='TOTAL = '.$regs->count();}
        return $repl;
    }

    private function resultHash(int $runId): string
    {
        $rows=DB::connection('exam')->table('non_cadre_allocation_results')->where('allocation_run_id',$runId)->orderBy('common_merit_position')->get()->map(fn($r)=>[$r->registration_id,$r->post_code,$r->choice_position,$r->allocation_basis])->all();
        return hash('sha256',json_encode($rows));
    }
}
