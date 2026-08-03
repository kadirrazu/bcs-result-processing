<?php
namespace App\Http\Controllers;

use App\Jobs\ApproveVivaMappingImport;
use App\Jobs\ApproveVivaBoardImport;
use App\Jobs\ProcessVivaBoardImport;
use App\Jobs\ValidateVivaBoardImport;
use App\Jobs\ProcessVivaMappingImport;
use App\Jobs\ValidateVivaMappingImport;
use App\Models\ImportCorrectionEntry;
use App\Models\VivaCandidateMapping;
use App\Models\VivaImportBatch;
use App\Models\VivaProcessingAudit;
use App\Models\VivaProcessingState;
use App\Models\VivaResult;
use App\Models\WrittenProcessingState;
use App\Models\WrittenResult;
use App\Services\Imports\InvalidRowCorrectionService;
use App\Services\Viva\VivaAuditService;
use App\Services\Viva\VivaMappingImportService;
use App\Services\Viva\VivaBoardImportService;
use App\Services\Viva\VivaRuleConfig;
use App\Services\Viva\VivaTemplateService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class VivaController extends Controller
{
    public function index(VivaRuleConfig $rules): View
    {
        $this->authorize('viewAny', VivaResult::class);
        $state=VivaProcessingState::query()->firstOrCreate(['id'=>1],['status'=>'not_started']);
        $writtenState=WrittenProcessingState::query()->first();
        $writtenReady=(bool)$writtenState?->result_finalized_at && !(bool)$writtenState?->is_stale;
        return view('viva.index',[
            'state'=>$state,'writtenReady'=>$writtenReady,'writtenFinalizedAt'=>$writtenState?->result_finalized_at,
            'counts'=>[
                'written_eligible'=>$writtenReady?WrittenResult::query()->where('status','active')->whereNotNull('written_qualified_track')->whereNotNull('finalized_at')->count():0,
                'mapped'=>VivaCandidateMapping::query()->count(),'results'=>VivaResult::query()->count(),'warnings'=>VivaResult::query()->where('validation_status','warning')->count(),
                'quota_mismatch'=>VivaResult::query()->where('quota_mismatch',true)->count(),'source_review'=>VivaResult::query()->where(fn($q)=>$q->where('invalid_flag',true)->orWhere('issue_flag',true))->count(),'high_mark'=>VivaResult::query()->where('high_mark_review',true)->count(),
            ],
            'latestMappingBatch'=>VivaImportBatch::query()->where('import_type','mapping')->latest('id')->first(),
            'latestBoardBatch'=>VivaImportBatch::query()->where('import_type','board')->latest('id')->first(),
            'recentBatches'=>VivaImportBatch::query()->latest('id')->limit(10)->get(),'audits'=>VivaProcessingAudit::query()->latest('id')->limit(10)->get(),
            'ruleSummary'=>['full_mark'=>$rules->fullMark(),'pass_percent'=>$rules->passPercent(),'pass_mark'=>$rules->passMark(),'high_mark_percent'=>$rules->highMarkReviewPercent(),'high_mark_mark'=>$rules->highMarkReviewMark()],
        ]);
    }

    public function storeMapping(Request $request,VivaMappingImportService $service,VivaAuditService $audit,ExaminationContext $context):RedirectResponse
    {
        $this->authorize('process',VivaResult::class);
        $state=WrittenProcessingState::query()->first(); abort_unless($state?->result_finalized_at && !$state?->is_stale,409,'Finalize the current Written result before importing Viva candidate mapping.');
        $v=$request->validate(['file'=>['required','file','mimes:xlsx,csv','max:102400']]);$exam=$context->currentId();abort_if($exam===null,409,'No examination is selected.');
        $batch=$service->enqueue($v['file'],$request->user()->id,$exam);$audit->record('VIVA_MAPPING_IMPORT_QUEUED',$request->user(),null,'queued',summary:['original_name'=>$batch->original_name],batchId:$batch->id);
        return redirect()->route('viva.mapping.result',$batch)->with('success','Viva candidate mapping file queued for staging.');
    }

    public function mappingResult(Request $request,VivaImportBatch $batch):View
    {
        $this->authorize('viewAny',VivaResult::class);abort_unless($batch->import_type==='mapping',404);
        $validation=trim((string)$request->query('validation','all'));$search=trim((string)$request->query('search',''));
        $rows=$batch->mappingRows()->when($validation!==''&&$validation!=='all',fn($q)=>$q->where('validation_status',$validation))->when($search!=='',fn($q)=>$q->where(fn($n)=>$n->where('reg',$search)->orWhere('user_id',$search)->orWhere('code',$search)))->orderByRaw("CASE validation_status WHEN 'identity_conflict' THEN 0 WHEN 'invalid' THEN 1 WHEN 'valid' THEN 2 ELSE 3 END")->orderBy('source_row')->paginate(100)->withQueryString();
        return view('viva.mapping-result',['record'=>$batch,'rows'=>$rows,'validation'=>$validation,'search'=>$search,'corrections'=>ImportCorrectionEntry::query()->where('module','viva_mapping')->where('batch_id',$batch->id)->latest('id')->limit(10)->get()]);
    }

    public function validateMapping(Request $request,VivaImportBatch $batch,ExaminationContext $context,VivaAuditService $audit):RedirectResponse
    {$this->authorize('process',VivaResult::class);abort_unless($batch->import_type==='mapping'&&in_array($batch->status,['staged','validated','failed'],true)&&(int)$batch->staged_rows>0,409,'Candidate mapping must be staged before validation.');$exam=$context->currentId();abort_if($exam===null,409);$before=$batch->status;$batch->update(['status'=>'validation_queued','progress_percent'=>0,'failure_message'=>null,'finished_at'=>null]);ValidateVivaMappingImport::dispatch($exam,$batch->id,$request->user()->id);$audit->record('VIVA_MAPPING_VALIDATION_QUEUED',$request->user(),$before,'validation_queued',batchId:$batch->id);return back()->with('success','Viva candidate mapping validation queued.');}

    public function approveMapping(Request $request,VivaImportBatch $batch,ExaminationContext $context,VivaAuditService $audit):RedirectResponse
    {$this->authorize('process',VivaResult::class);abort_unless($batch->import_type==='mapping'&&$batch->status==='validated',409,'Only validated Viva candidate mapping data can be approved.');$exam=$context->currentId();abort_if($exam===null,409);$before=$batch->status;$batch->update(['status'=>'approval_queued','progress_percent'=>0,'failure_message'=>null,'finished_at'=>null]);ApproveVivaMappingImport::dispatch($exam,$batch->id,$request->user()->id);$audit->record('VIVA_MAPPING_APPROVAL_QUEUED',$request->user(),$before,'approval_queued',batchId:$batch->id);return back()->with('success','Valid Viva candidate mappings queued for approval.');}

    public function retryMapping(Request $request,VivaImportBatch $batch,ExaminationContext $context,VivaAuditService $audit):RedirectResponse
    {$this->authorize('process',VivaResult::class);abort_unless($batch->import_type==='mapping'&&$batch->status==='failed'&&(int)$batch->approved_rows===0,409);$exam=$context->currentId();abort_if($exam===null,409);$before=$batch->status;$batch->update(['status'=>'queued','failure_message'=>null,'processed_rows'=>0,'staged_rows'=>0,'valid_rows'=>0,'warning_rows'=>0,'invalid_rows'=>0,'identity_conflict_rows'=>0,'progress_percent'=>0,'finished_at'=>null]);ProcessVivaMappingImport::dispatch($exam,$batch->id,$request->user()->id);$audit->record('VIVA_MAPPING_RETRY_QUEUED',$request->user(),$before,'queued',batchId:$batch->id);return back()->with('success','Viva candidate mapping staging retry queued.');}

    public function mappingStatus(VivaImportBatch $batch):JsonResponse
    {$this->authorize('viewAny',VivaResult::class);abort_unless($batch->import_type==='mapping',404);$batch->refresh();return response()->json(['status'=>$batch->status,'total_rows'=>(int)$batch->total_rows,'processed_rows'=>(int)$batch->processed_rows,'staged_rows'=>(int)$batch->staged_rows,'valid_rows'=>(int)$batch->valid_rows,'invalid_rows'=>(int)$batch->invalid_rows,'identity_conflict_rows'=>(int)$batch->identity_conflict_rows,'approved_rows'=>(int)$batch->approved_rows,'inserted_rows'=>(int)$batch->inserted_rows,'updated_rows'=>(int)$batch->updated_rows,'progress_percent'=>(float)$batch->progress_percent,'failure_message'=>$batch->failure_message,'finished'=>!in_array($batch->status,['queued','staging','validation_queued','validating','approval_queued','approving'],true)]);}

    public function mappingCorrectionTemplate(VivaImportBatch $batch,InvalidRowCorrectionService $service):BinaryFileResponse
    {$this->authorize('viewAny',VivaResult::class);abort_unless($batch->import_type==='mapping',404);$dir=storage_path('app/private/import-corrections');File::ensureDirectoryExists($dir);$path=$dir.'/viva-mapping-batch-'.$batch->id.'-invalid-rows.xlsx';$count=$service->createCorrectionWorkbook('viva_mapping',$batch->id,$path);abort_if($count===0,409,'This batch has no invalid rows to correct.');return response()->download($path,basename($path))->deleteFileAfterSend();}

    public function applyMappingCorrections(Request $request,VivaImportBatch $batch,InvalidRowCorrectionService $service,ExaminationContext $context,VivaAuditService $audit):RedirectResponse
    {$this->authorize('process',VivaResult::class);$v=$request->validate(['correction_file'=>['required','file','mimes:xlsx,csv','max:102400']]);$exam=$context->currentId();abort_if($exam===null,409);$before=$batch->status;$summary=$service->apply('viva_mapping',$batch,$v['correction_file'],$request->user());$batch->update(['status'=>'validation_queued','progress_percent'=>0,'failure_message'=>null,'finished_at'=>null]);ValidateVivaMappingImport::dispatch($exam,$batch->id,$request->user()->id);$audit->record('VIVA_MAPPING_INVALID_ROWS_CORRECTED',$request->user(),$before,'validation_queued','Corrected invalid Viva mapping source rows.',summary:$summary,batchId:$batch->id);return back()->with('success',number_format($summary['corrected_rows']).' invalid mapping row(s) corrected. Validation is running again.');}


    public function storeBoard(Request $request,VivaBoardImportService $service,VivaAuditService $audit,ExaminationContext $context):RedirectResponse
    {$this->authorize('process',VivaResult::class);abort_if(VivaCandidateMapping::query()->doesntExist(),409,'Approve Viva Candidate Mapping before importing Board data.');$v=$request->validate(['file'=>['required','file','mimes:xlsx,csv','max:102400']]);$exam=$context->currentId();abort_if($exam===null,409);$batch=$service->enqueue($v['file'],$request->user()->id,$exam);$audit->record('VIVA_BOARD_IMPORT_QUEUED',$request->user(),null,'queued',summary:['original_name'=>$batch->original_name],batchId:$batch->id);return redirect()->route('viva.board.result',$batch)->with('success','Viva Board data file queued for staging.');}

    public function boardResult(Request $request,VivaImportBatch $batch):View
    {$this->authorize('viewAny',VivaResult::class);abort_unless($batch->import_type==='board',404);$validation=trim((string)$request->query('validation','all'));$search=trim((string)$request->query('search',''));$rows=$batch->boardRows()->when($validation!==''&&$validation!=='all',fn($q)=>$q->where('validation_status',$validation))->when($search!=='',fn($q)=>$q->where(fn($n)=>$n->where('code',$search)->orWhere('member_id',$search)))->orderByRaw("CASE validation_status WHEN 'invalid' THEN 0 WHEN 'warning' THEN 1 WHEN 'valid' THEN 2 ELSE 3 END")->orderBy('source_row')->paginate(100)->withQueryString();return view('viva.board-result',['record'=>$batch,'rows'=>$rows,'validation'=>$validation,'search'=>$search,'corrections'=>ImportCorrectionEntry::query()->where('module','viva_board')->where('batch_id',$batch->id)->latest('id')->limit(10)->get()]);}

    public function validateBoard(Request $request,VivaImportBatch $batch,ExaminationContext $context,VivaAuditService $audit):RedirectResponse
    {$this->authorize('process',VivaResult::class);abort_unless($batch->import_type==='board'&&in_array($batch->status,['staged','validated','failed'],true)&&(int)$batch->staged_rows>0,409,'Board data must be staged before validation.');$exam=$context->currentId();abort_if($exam===null,409);$before=$batch->status;$batch->update(['status'=>'validation_queued','progress_percent'=>0,'failure_message'=>null,'finished_at'=>null]);ValidateVivaBoardImport::dispatch($exam,$batch->id,$request->user()->id);$audit->record('VIVA_BOARD_VALIDATION_QUEUED',$request->user(),$before,'validation_queued',batchId:$batch->id);return back()->with('success','Viva Board data validation queued.');}

    public function approveBoard(Request $request,VivaImportBatch $batch,ExaminationContext $context,VivaAuditService $audit):RedirectResponse
    {$this->authorize('process',VivaResult::class);abort_unless($batch->import_type==='board'&&$batch->status==='validated',409,'Only validated Viva Board data can be approved.');$exam=$context->currentId();abort_if($exam===null,409);$before=$batch->status;$batch->update(['status'=>'approval_queued','progress_percent'=>0,'failure_message'=>null,'finished_at'=>null]);ApproveVivaBoardImport::dispatch($exam,$batch->id,$request->user()->id);$audit->record('VIVA_BOARD_APPROVAL_QUEUED',$request->user(),$before,'approval_queued',batchId:$batch->id);return back()->with('success','Valid and warning Viva Board rows queued for approval.');}

    public function retryBoard(Request $request,VivaImportBatch $batch,ExaminationContext $context,VivaAuditService $audit):RedirectResponse
    {$this->authorize('process',VivaResult::class);abort_unless($batch->import_type==='board'&&$batch->status==='failed'&&(int)$batch->approved_rows===0,409);$exam=$context->currentId();abort_if($exam===null,409);$before=$batch->status;$batch->update(['status'=>'queued','failure_message'=>null,'processed_rows'=>0,'staged_rows'=>0,'valid_rows'=>0,'warning_rows'=>0,'invalid_rows'=>0,'progress_percent'=>0,'finished_at'=>null]);ProcessVivaBoardImport::dispatch($exam,$batch->id,$request->user()->id);$audit->record('VIVA_BOARD_RETRY_QUEUED',$request->user(),$before,'queued',batchId:$batch->id);return back()->with('success','Viva Board staging retry queued.');}

    public function boardStatus(VivaImportBatch $batch):JsonResponse
    {$this->authorize('viewAny',VivaResult::class);abort_unless($batch->import_type==='board',404);$batch->refresh();return response()->json(['status'=>$batch->status,'total_rows'=>(int)$batch->total_rows,'processed_rows'=>(int)$batch->processed_rows,'staged_rows'=>(int)$batch->staged_rows,'valid_rows'=>(int)$batch->valid_rows,'warning_rows'=>(int)$batch->warning_rows,'invalid_rows'=>(int)$batch->invalid_rows,'approved_rows'=>(int)$batch->approved_rows,'inserted_rows'=>(int)$batch->inserted_rows,'updated_rows'=>(int)$batch->updated_rows,'progress_percent'=>(float)$batch->progress_percent,'failure_message'=>$batch->failure_message,'finished'=>!in_array($batch->status,['queued','staging','validation_queued','validating','approval_queued','approving'],true)]);}

    public function boardCorrectionTemplate(VivaImportBatch $batch,InvalidRowCorrectionService $service):BinaryFileResponse
    {$this->authorize('viewAny',VivaResult::class);abort_unless($batch->import_type==='board',404);$dir=storage_path('app/private/import-corrections');File::ensureDirectoryExists($dir);$path=$dir.'/viva-board-batch-'.$batch->id.'-invalid-rows.xlsx';$count=$service->createCorrectionWorkbook('viva_board',$batch->id,$path);abort_if($count===0,409,'This batch has no invalid rows to correct.');return response()->download($path,basename($path))->deleteFileAfterSend();}

    public function applyBoardCorrections(Request $request,VivaImportBatch $batch,InvalidRowCorrectionService $service,ExaminationContext $context,VivaAuditService $audit):RedirectResponse
    {$this->authorize('process',VivaResult::class);$v=$request->validate(['correction_file'=>['required','file','mimes:xlsx,csv','max:102400']]);$exam=$context->currentId();abort_if($exam===null,409);$before=$batch->status;$summary=$service->apply('viva_board',$batch,$v['correction_file'],$request->user());$batch->update(['status'=>'validation_queued','progress_percent'=>0,'failure_message'=>null,'finished_at'=>null]);ValidateVivaBoardImport::dispatch($exam,$batch->id,$request->user()->id);$audit->record('VIVA_BOARD_INVALID_ROWS_CORRECTED',$request->user(),$before,'validation_queued','Corrected invalid Viva Board source rows.',summary:$summary,batchId:$batch->id);return back()->with('success',number_format($summary['corrected_rows']).' invalid Board row(s) corrected. Validation is running again.');}

    public function mappingTemplate(VivaTemplateService $service):BinaryFileResponse{$this->authorize('viewAny',VivaResult::class);$d=storage_path('app/private/viva');File::ensureDirectoryExists($d);$p=$d.'/viva-candidate-mapping-template.xlsx';$service->createMappingTemplate($p);return response()->download($p,basename($p))->deleteFileAfterSend();}
    public function boardTemplate(VivaTemplateService $service):BinaryFileResponse{$this->authorize('viewAny',VivaResult::class);$d=storage_path('app/private/viva');File::ensureDirectoryExists($d);$p=$d.'/viva-board-data-template.xlsx';$service->createBoardTemplate($p);return response()->download($p,basename($p))->deleteFileAfterSend();}
}
