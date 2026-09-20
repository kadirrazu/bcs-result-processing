<?php

namespace App\Services\NonCadre\Choice;

use App\Enums\RegistrationStatus;
use App\Jobs\ProcessNonCadreChoiceImport;
use App\Models\AllocationA5CandidateResult;
use App\Models\AllocationA5Run;
use App\Models\MeritResult;
use App\Models\Registration;
use App\Services\Merit\MeritFinalizedDatasetService;
use App\Services\NonCadre\NonCadreReadinessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class NonCadreChoiceService
{
    public function __construct(
        private readonly NonCadreReadinessService $readiness,
        private readonly MeritFinalizedDatasetService $merit,
    ) {}

    public function maxChoices(): int { return max(1, (int) config('non-cadre.choice.max_options', 20)); }

    /** @return list<string> */
    public function headers(?int $max = null): array
    {
        $headers=['user','reg'];
        for($i=1;$i<=($max??$this->maxChoices());$i++) $headers[]='opt'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        return $headers;
    }

    public function templatePath(): string
    {
        $sheet=new Spreadsheet(); $ws=$sheet->getActiveSheet();
        foreach($this->headers() as $i=>$header) $ws->setCellValue([$i+1,1],$header);
        $ws->setCellValue('A2','0000000001'); $ws->setCellValue('B2','00000001');
        $ws->freezePane('C2'); $ws->getStyle('A1:'.$ws->getHighestColumn().'1')->getFont()->setBold(true);
        foreach(range(1,count($this->headers())) as $c) $ws->getColumnDimensionByColumn($c)->setAutoSize(true);
        $path=storage_path('app/non-cadre-choice-template-'.bin2hex(random_bytes(5)).'.xlsx');
        (new Xlsx($sheet))->save($path); $sheet->disconnectWorksheets(); return $path;
    }

    public function enqueue(UploadedFile $file, ?int $actorId, int $examinationId): object
    {
        $this->readiness->requireReady(); $circular=$this->effectiveCircular(); $this->effectiveSeatBreakup();
        $extension=strtolower($file->getClientOriginalExtension());
        $stored=sprintf('non-cadre/choice-imports/%s-%s.%s',now()->format('YmdHis'),bin2hex(random_bytes(8)),$extension);
        $file->storeAs(dirname($stored),basename($stored),'local');
        $hash=hash_file('sha256',Storage::disk('local')->path($stored)) ?: null;
        $id=DB::connection('exam')->transaction(function()use($file,$stored,$hash,$actorId,$circular){
            $version=((int)DB::connection('exam')->table('non_cadre_choice_imports')->max('version'))+1;
            return DB::connection('exam')->table('non_cadre_choice_imports')->insertGetId([
                'version'=>$version,'circular_version_id'=>$circular->id,'status'=>'queued','source_filename'=>$file->getClientOriginalName(),
                'stored_filename'=>$stored,'source_hash'=>$hash,'max_choices'=>$this->maxChoices(),'total_rows'=>0,'processed_rows'=>0,
                'valid_rows'=>0,'invalid_rows'=>0,'progress_percent'=>0,'uploaded_by'=>$actorId,'queued_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
        ProcessNonCadreChoiceImport::dispatch($examinationId,$id,$actorId);
        return DB::connection('exam')->table('non_cadre_choice_imports')->find($id);
    }

    public function processQueuedImport(int $importId, ?int $actorId): object
    {
        $import=DB::connection('exam')->table('non_cadre_choice_imports')->find($importId); if(!$import) throw new RuntimeException('Non-Cadre Choice import not found.');
        DB::connection('exam')->table('non_cadre_choice_imports')->where('id',$importId)->update(['status'=>'processing','started_at'=>now(),'failure_message'=>null,'progress_percent'=>1,'updated_at'=>now()]);
        try {
            $this->readiness->requireReady(); $circular=$this->effectiveCircular(); $this->effectiveSeatBreakup();
            if((int)$import->circular_version_id!==(int)$circular->id) throw new RuntimeException('NC1 Circular changed before queued Choice validation started. Re-upload the Choice file.');
            $path=Storage::disk('local')->path((string)$import->stored_filename); if(!is_file($path)) throw new RuntimeException('Queued Non-Cadre Choice file is missing.');
            $merit=$this->merit->verifiedSummary(); $max=(int)$import->max_choices;
            $book=IOFactory::load($path); $sheet=$book->getActiveSheet(); $rows=$sheet->toArray(null,true,true,false);
            $header=array_map(static fn($v)=>strtolower(trim((string)$v)),array_shift($rows)??[]);
            if($header!==$this->headers($max)) throw new RuntimeException('Choice header must be exactly: '.implode(', ',$this->headers($max)).'.');
            $rows=array_values(array_filter($rows,fn($r)=>!collect($r)->every(fn($v)=>$v===null||trim((string)$v)==='')));
            $duplicateRegs=[];$regFirst=[];foreach($rows as $offset=>$values){$candidateReg=trim((string)($values[1]??''));if($candidateReg==='')continue;$line=$offset+2;if(isset($regFirst[$candidateReg]))$duplicateRegs[]="reg={$candidateReg} at rows {$regFirst[$candidateReg]} and {$line}";else$regFirst[$candidateReg]=$line;}if($duplicateRegs!==[])throw new RuntimeException('Duplicate registration rows are not allowed: '.implode('; ',$duplicateRegs));
            DB::connection('exam')->table('non_cadre_choice_imports')->where('id',$importId)->update(['total_rows'=>count($rows),'progress_percent'=>5,'updated_at'=>now()]);
            $posts=DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$circular->id)->whereRaw('UPPER(status) = ?',['ACTIVE'])->get()->keyBy(fn($p)=>(string)$p->post_code);
            $a5=AllocationA5Run::query()->where('status','finalized')->where('is_stale',false)->latest('version')->firstOrFail();
            $allocatedIds=AllocationA5CandidateResult::query()->where('allocation_a5_run_id',$a5->id)->where('overall_status','PASS')->pluck('registration_id')->map(fn($v)=>(int)$v)->flip();
            $valid=0;$invalid=0;$processed=0;$total=max(1,count($rows));
            DB::connection('exam')->table('non_cadre_choice_items')->where('choice_import_id',$importId)->delete();
            foreach($rows as $offset=>$values){
                $line=$offset+2;$user=trim((string)($values[0]??''));$reg=trim((string)($values[1]??''));$raw=[];for($i=0;$i<$max;$i++)$raw[]=trim((string)($values[$i+2]??''));
                $rowErrors=[];$rejections=[];$validated=[];$used=[];
                $registration=($user!==''&&$reg!=='')?Registration::query()->where('user_id', $user)->where('reg', $reg)->first():null;
                if(!$registration)$rowErrors[]='user + reg do not match the authoritative Registration data.';
                $meritRow=$registration?MeritResult::query()->where('processing_run_id',(int)$merit['processing_run_id'])->where('registration_id',$registration->id)->where('common_merit_eligible', true)->first():null;
                if($registration&&!$meritRow)$rowErrors[]='Candidate is not in the finalized Common Merit eligible population (Written/Viva qualified Cadre-allocation population).';
                if($registration&&$registration->status!==RegistrationStatus::Active)$rowErrors[]='Registration is not ACTIVE.';
                foreach($raw as $idx=>$code){if($code==='')continue;$pos=$idx+1;if(isset($used[$code])){$rejections[]=[$pos,$code,'DUPLICATE_CHOICE',"Duplicate choice {$code}; first occurrence retained."];continue;}$used[$code]=true;$post=$posts->get($code);if(!$post){$rejections[]=[$pos,$code,'INVALID_POST_CODE',"Post code {$code} is not ACTIVE in the current finalized Non-Cadre Circular."];continue;}$allowed=array_values(array_filter(array_map('trim',explode('|',(string)($post->bachelor_subject_codes??'')))));$subject=trim((string)($registration?->bachelor_subject_code??''));if($allowed!==[]&&!in_array($subject,$allowed,true)){$rejections[]=[$pos,$code,'BACHELOR_SUBJECT_MISMATCH',"Post {$code} does not allow candidate Bachelor Subject {$subject}."];continue;}$validated[]=$code;}
                $historical=$registration?isset($allocatedIds[(int)$registration->id]):false;$empty=count(array_filter($raw,fn($v)=>$v!==''))===0;
                $population=$rowErrors!==[]?'INVALID':($historical?'CADRE_ALLOCATED_HISTORICAL_ONLY':($empty?'EMPTY_CHOICE':($validated===[]?'NO_VALID_CHOICE':'ALLOCATION_ELIGIBLE')));
                $status=$rowErrors===[]?'valid':'invalid';$status==='valid'?$valid++:$invalid++;
                $itemId=DB::connection('exam')->table('non_cadre_choice_items')->insertGetId(['choice_import_id'=>$importId,'registration_id'=>$registration?->id,'user_id'=>$user,'reg'=>$reg,'original_choices'=>json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'validated_choices'=>json_encode($validated),'effective_choices'=>json_encode($validated),'validation_status'=>$status,'validation_summary'=>json_encode(['row'=>$line,'errors'=>$rowErrors,'population_status'=>$population,'cadre_allocated'=>$historical,'empty_choice'=>$empty,'common_merit_position'=>$meritRow?->common_merit_position,'written_passed'=>(bool)$meritRow,'viva_passed'=>(bool)$meritRow],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'created_at'=>now(),'updated_at'=>now()]);
                foreach($rejections as [$pos,$code,$reasonCode,$reason])DB::connection('exam')->table('non_cadre_choice_rejections')->insert(['choice_item_id'=>$itemId,'choice_position'=>$pos,'post_code'=>$code,'reason_code'=>$reasonCode,'reason'=>$reason,'created_at'=>now()]);
                $processed++; if($processed%100===0||$processed===$total){$pct=min(99,5+(int)floor(($processed/$total)*94));DB::connection('exam')->table('non_cadre_choice_imports')->where('id',$importId)->update(['processed_rows'=>$processed,'valid_rows'=>$valid,'invalid_rows'=>$invalid,'progress_percent'=>$pct,'updated_at'=>now()]);}
            }
            $book->disconnectWorksheets();
            $finalStatus=$invalid>0?'needs_review':'validated';
            DB::connection('exam')->table('non_cadre_choice_imports')->where('id',$importId)->update(['status'=>$finalStatus,'processed_rows'=>$processed,'valid_rows'=>$valid,'invalid_rows'=>$invalid,'progress_percent'=>100,'finished_at'=>now(),'updated_at'=>now()]);
            $this->audit('import_validated',$importId,null,['rows'=>$processed,'valid'=>$valid,'invalid'=>$invalid,'max_choices'=>$max,'merit_run_id'=>$merit['processing_run_id'],'cadre_a5_run_id'=>$a5->id],$actorId);
            return DB::connection('exam')->table('non_cadre_choice_imports')->find($importId);
        } catch(Throwable $e){DB::connection('exam')->table('non_cadre_choice_imports')->where('id',$importId)->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,65000),'finished_at'=>now(),'updated_at'=>now()]);throw $e;}
    }

    public function finalize(int $importId, ?int $actorId): object
    {
        $this->readiness->requireReady();$circular=$this->effectiveCircular();$this->effectiveSeatBreakup();
        return DB::connection('exam')->transaction(function()use($importId,$actorId,$circular){$import=DB::connection('exam')->table('non_cadre_choice_imports')->lockForUpdate()->find($importId);if(!$import)abort(404);if((int)$import->circular_version_id!==(int)$circular->id)throw ValidationException::withMessages(['choice'=>'Choice import is not bound to the current finalized Non-Cadre Circular. Re-import choices.']);if((int)$import->invalid_rows>0||!in_array((string)$import->status,['validated','finalized'],true))throw ValidationException::withMessages(['choice'=>'Resolve/re-upload invalid choice rows before finalization.']);$hash=$this->hashItems($importId);DB::connection('exam')->table('non_cadre_choice_imports')->where('id','<>',$importId)->where('status','finalized')->update(['status'=>'outdated','is_stale'=>true,'stale_reason'=>'A newer Non-Cadre Choice dataset was finalized.','staled_at'=>now(),'updated_at'=>now()]);DB::connection('exam')->table('non_cadre_choice_imports')->where('id',$importId)->update(['status'=>'finalized','dataset_hash'=>$hash,'is_stale'=>false,'stale_reason'=>null,'staled_at'=>null,'finalized_by'=>$actorId,'finalized_at'=>now(),'updated_at'=>now()]);$reason='NC3 Choice dataset changed/finalized. Re-run NC4 Allocation before NC5 Reporting.';DB::connection('exam')->table('non_cadre_allocation_runs')->where('is_stale',false)->update(['is_stale'=>true,'stale_reason'=>$reason,'staled_at'=>now(),'updated_at'=>now()]);DB::connection('exam')->table('non_cadre_processing_states')->where('id',1)->update(['choice_status'=>'finalized','allocation_status'=>'stale','reporting_status'=>'stale','updated_at'=>now()]);$this->audit('dataset_finalized',$importId,['status'=>$import->status],['status'=>'finalized','dataset_hash'=>$hash],$actorId);return DB::connection('exam')->table('non_cadre_choice_imports')->find($importId);});
    }

    public function toggleChoice(int $itemId,string $postCode,string $action,string $reason,?int $actorId):void
    {
        if(!in_array($action,['exclude','restore'],true))abort(422);if(trim($reason)==='')throw ValidationException::withMessages(['reason'=>'A mandatory operator reason is required.']);
        DB::connection('exam')->transaction(function()use($itemId,$postCode,$action,$reason,$actorId){$item=DB::connection('exam')->table('non_cadre_choice_items')->lockForUpdate()->find($itemId);if(!$item)abort(404);$import=DB::connection('exam')->table('non_cadre_choice_imports')->find($item->choice_import_id);if(!$import||$import->status!=='finalized'||$import->is_stale)throw ValidationException::withMessages(['choice'=>'Manual adjustment is allowed only on the current finalized NC3 dataset.']);$validated=$this->jsonList($item->validated_choices);if(!in_array($postCode,$validated,true))throw ValidationException::withMessages(['choice'=>'Only a validated existing choice may be excluded/restored.']);$before=$this->jsonList($item->effective_choices);$after=$before;if($action==='exclude')$after=array_values(array_filter($before,fn($c)=>$c!==$postCode));else{$after=array_values(array_filter($validated,fn($c)=>in_array($c,$before,true)||$c===$postCode));}$after=array_values(array_filter($validated,fn($c)=>in_array($c,$after,true)));if($after===$before)return;DB::connection('exam')->table('non_cadre_choice_adjustments')->insert(['choice_item_id'=>$itemId,'before_choices'=>json_encode($before),'after_choices'=>json_encode($after),'reason'=>trim($reason),'actor_id'=>$actorId,'created_at'=>now()]);DB::connection('exam')->table('non_cadre_choice_items')->where('id',$itemId)->update(['effective_choices'=>json_encode($after),'updated_at'=>now()]);$hash=$this->hashItems((int)$import->id);DB::connection('exam')->table('non_cadre_choice_imports')->where('id',$import->id)->update(['dataset_hash'=>$hash,'updated_at'=>now()]);$stale='NC3 effective choice was manually adjusted. Re-run NC4 Allocation.';DB::connection('exam')->table('non_cadre_allocation_runs')->where('is_stale',false)->update(['is_stale'=>true,'stale_reason'=>$stale,'staled_at'=>now(),'updated_at'=>now()]);DB::connection('exam')->table('non_cadre_processing_states')->where('id',1)->update(['allocation_status'=>'stale','reporting_status'=>'stale','updated_at'=>now()]);$this->audit(strtoupper($action),$itemId,['choices'=>$before],['choices'=>$after,'post_code'=>$postCode,'reason'=>trim($reason)],$actorId);});
    }

    public function paginatedItems(int $importId,string $search='',string $filter='all'):LengthAwarePaginator
    {
        $q=DB::connection('exam')->table('non_cadre_choice_items as i')->where('i.choice_import_id',$importId)->select('i.*');
        if($search!=='')$q->where(fn($x)=>$x->where('i.reg','like','%'.$search.'%')->orWhere('i.user_id','like','%'.$search.'%'));
        $map=['eligible'=>'ALLOCATION_ELIGIBLE','historical'=>'CADRE_ALLOCATED_HISTORICAL_ONLY','not_eligible'=>'NO_VALID_CHOICE','empty'=>'EMPTY_CHOICE','invalid'=>'INVALID'];
        if(isset($map[$filter]))$q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(i.validation_summary, '$.population_status')) = ?",[$map[$filter]]);
        if($filter==='adjusted')$q->whereColumn('i.validated_choices','<>','i.effective_choices');
        return $q->orderBy('i.reg')->paginate(20)->withQueryString();
    }

    public function itemDetails(int $itemId):array
    {
        $item=DB::connection('exam')->table('non_cadre_choice_items')->find($itemId);if(!$item)abort(404);
        $import=DB::connection('exam')->table('non_cadre_choice_imports')->find($item->choice_import_id);if(!$import)abort(404);
        $rejections=DB::connection('exam')->table('non_cadre_choice_rejections')->where('choice_item_id',$itemId)->orderBy('choice_position')->get();
        $history=DB::connection('exam')->table('non_cadre_choice_adjustments')->where('choice_item_id',$itemId)->orderByDesc('created_at')->orderByDesc('id')->get();
        $postMap=$this->postMap((int)$import->circular_version_id);$registration=$item->registration_id?Registration::query()->find($item->registration_id):null;
        return compact('item','import','rejections','history','postMap','registration');
    }

    public function postMap(int $circularVersionId):Collection{return DB::connection('exam')->table('non_cadre_circular_posts')->where('circular_version_id',$circularVersionId)->get()->keyBy(fn($p)=>(string)$p->post_code);}
    public function summary(int $importId):array{$items=DB::connection('exam')->table('non_cadre_choice_items')->where('choice_import_id',$importId)->get();$c=['total'=>$items->count(),'allocation_eligible'=>0,'historical_only'=>0,'empty'=>0,'no_valid_choice'=>0,'invalid'=>0,'adjusted_candidates'=>0,'excluded_choices'=>0];foreach($items as $i){$s=json_decode((string)$i->validation_summary,true)?:[];$p=$s['population_status']??'INVALID';if($p==='ALLOCATION_ELIGIBLE')$c['allocation_eligible']++;elseif($p==='CADRE_ALLOCATED_HISTORICAL_ONLY')$c['historical_only']++;elseif($p==='EMPTY_CHOICE')$c['empty']++;elseif($p==='NO_VALID_CHOICE')$c['no_valid_choice']++;else$c['invalid']++;$v=$this->jsonList($i->validated_choices);$e=$this->jsonList($i->effective_choices);if($v!==$e){$c['adjusted_candidates']++;$c['excluded_choices']+=max(0,count($v)-count($e));}}return$c;}

    private function effectiveCircular():object{$v=DB::connection('exam')->table('non_cadre_circular_versions')->where('status','finalized')->where('is_stale',false)->latest('version')->first();if(!$v)throw ValidationException::withMessages(['choice'=>'Finalize a current NC1 Circular first.']);return$v;}
    private function effectiveSeatBreakup():object{$v=DB::connection('exam')->table('non_cadre_seat_breakup_versions')->where('status','finalized')->where('is_stale',false)->latest('version')->first();if(!$v)throw ValidationException::withMessages(['choice'=>'Finalize a current NC2 Seat Breakup first.']);return$v;}
    private function hashItems(int$id):string{$rows=DB::connection('exam')->table('non_cadre_choice_items')->where('choice_import_id',$id)->orderBy('reg')->get()->map(fn($r)=>[(string)$r->user_id,(string)$r->reg,$this->jsonList($r->original_choices),$this->jsonList($r->validated_choices),$this->jsonList($r->effective_choices),(string)$r->validation_status])->all();return hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
    /** @return list<string> */private function jsonList(mixed$v):array{$v=is_array($v)?$v:json_decode((string)$v,true);return array_values(array_map('strval',is_array($v)?$v:[]));}
    private function audit(string$action,int$id,?array$before,?array$after,?int$actor):void{DB::connection('exam')->table('non_cadre_processing_audits')->insert(['stage'=>'choice','action'=>$action,'entity_type'=>'choice','entity_id'=>$id,'before_payload'=>$before?json_encode($before):null,'after_payload'=>$after?json_encode($after):null,'actor_id'=>$actor,'created_at'=>now()]);}
}
