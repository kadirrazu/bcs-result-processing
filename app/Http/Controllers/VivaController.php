<?php
namespace App\Http\Controllers;

use App\Jobs\ApproveVivaMappingImport;
use App\Jobs\ApproveVivaBoardImport;
use App\Jobs\ProcessVivaBoardImport;
use App\Jobs\ValidateVivaBoardImport;
use App\Jobs\ProcessVivaMappingImport;
use App\Jobs\ValidateVivaMappingImport;
use App\Jobs\ProcessVivaReconciliation;
use App\Jobs\ProcessVivaResults;
use App\Models\ImportCorrectionEntry;
use App\Models\VivaCandidateMapping;
use App\Models\VivaImportBatch;
use App\Models\VivaProcessingAudit;
use App\Models\VivaProcessingState;
use App\Models\VivaProcessingRun;
use App\Models\VivaFinalizationRun;
use App\Models\VivaResult;
use App\Models\VivaReconciliationRun;
use App\Models\Registration;
use App\Models\WrittenProcessingState;
use App\Models\WrittenResult;
use App\Services\Imports\InvalidRowCorrectionService;
use App\Services\Viva\VivaAuditService;
use App\Services\Viva\VivaMappingImportService;
use App\Services\Viva\VivaManualCorrectionService;
use App\Services\Viva\VivaBoardImportService;
use App\Services\Viva\VivaRuleConfig;
use App\Services\Viva\VivaTemplateService;
use App\Services\Viva\VivaInternalResultExportService;
use App\Services\Viva\VivaFinalizationService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'viva_pass'=>VivaResult::query()->where('viva_result_status','pass')->count(),'viva_fail'=>VivaResult::query()->where('viva_result_status','fail')->count(),'viva_absent'=>VivaResult::query()->where('attendance_status','absent')->count(),
            ],
            'latestMappingBatch'=>VivaImportBatch::query()->where('import_type','mapping')->latest('id')->first(),
            'latestBoardBatch'=>VivaImportBatch::query()->where('import_type','board')->latest('id')->first(),
            'latestReconciliationRun'=>VivaReconciliationRun::query()->latest('id')->first(),
            'latestProcessingRun'=>VivaProcessingRun::query()->latest('id')->first(),
            'latestFinalizationRun'=>VivaFinalizationRun::query()->latest('id')->first(),
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


    public function generateReconciliation(Request $request, ExaminationContext $context, VivaAuditService $audit): RedirectResponse
    {
        $this->authorize('process', VivaResult::class);
        $writtenState = WrittenProcessingState::query()->first();
        abort_unless($writtenState?->result_finalized_at && ! $writtenState?->is_stale, 409, 'Finalize the current Written result before Viva reconciliation.');
        abort_if(VivaResult::query()->doesntExist(), 409, 'Approve Viva Board data before generating reconciliation.');
        abort_if(VivaReconciliationRun::query()->whereIn('status', ['queued', 'running'])->exists(), 409, 'A Viva reconciliation is already running.');

        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $run = VivaReconciliationRun::query()->create([
            'status' => 'queued',
            'created_by' => $request->user()->id,
        ]);

        ProcessVivaReconciliation::dispatch($examinationId, $run->id, $request->user()->id);
        $audit->record('VIVA_RECONCILIATION_QUEUED', $request->user(), (string) VivaProcessingState::query()->first()?->status?->value, 'reconciliation_running', summary: ['run_id' => $run->id]);

        return redirect()->route('viva.reconciliation.show', $run)->with('success', 'Viva reconciliation and review checks queued.');
    }

    public function reconciliationShow(VivaReconciliationRun $run): View
    {
        $this->authorize('viewAny', VivaResult::class);
        return view('viva.reconciliation', ['run' => $run]);
    }

    public function reconciliationStatus(VivaReconciliationRun $run): JsonResponse
    {
        $this->authorize('viewAny', VivaResult::class);
        $run->refresh();
        return response()->json([
            'status' => $run->status,
            'total_candidates' => (int) $run->total_candidates,
            'processed_candidates' => (int) $run->processed_candidates,
            'progress_percent' => (float) $run->progress_percent,
            'failure_message' => $run->failure_message,
            'finished' => ! in_array($run->status, ['queued', 'running'], true),
            'redirect_url' => route('viva.reconciliation.show', $run),
        ]);
    }

    public function reviews(Request $request): View
    {
        $this->authorize('viewAny', VivaResult::class);
        $review = (string) $request->query('review', 'all');
        $quota = strtoupper((string) $request->query('quota', ''));
        $direction = (string) $request->query('direction', '');
        $search = trim((string) $request->query('search', ''));

        $rows = VivaResult::query()
            ->join('registrations as r', 'r.id', '=', 'viva_results.registration_id')
            ->select('viva_results.*', 'r.name', 'r.has_ff_quota', 'r.has_em_quota', 'r.has_phc_quota')
            ->when($review === 'quota', fn ($q) => $q->where('viva_results.quota_mismatch', 1))
            ->when($review === 'source', fn ($q) => $q->where(fn ($n) => $n->where('viva_results.invalid_flag', 1)->orWhere('viva_results.issue_flag', 1)))
            ->when($review === 'high', fn ($q) => $q->where('viva_results.high_mark_review', 1))
            ->when(in_array($quota, ['CFF', 'EM', 'PHC'], true), fn ($q) => $q->whereRaw("JSON_EXTRACT(viva_results.quota_mismatch_details, '$.{$quota}') IS NOT NULL"))
            ->when(in_array($direction, ['registration_only', 'viva_only'], true), function ($q) use ($direction, $quota): void {
                if (in_array($quota, ['CFF', 'EM', 'PHC'], true)) {
                    $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(viva_results.quota_mismatch_details, '$.{$quota}.direction')) = ?", [$direction]);
                    return;
                }
                $q->where(function ($nested) use ($direction): void {
                    foreach (['CFF', 'EM', 'PHC'] as $type) {
                        $nested->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(viva_results.quota_mismatch_details, '$.{$type}.direction')) = ?", [$direction]);
                    }
                });
            })
            ->when($search !== '', fn ($q) => $q->where(fn ($n) => $n->where('viva_results.reg', $search)->orWhere('viva_results.user_id', $search)->orWhere('viva_results.code', $search)->orWhere('r.name', 'like', "%{$search}%")))
            ->orderByRaw('CASE WHEN viva_results.quota_mismatch = 1 OR viva_results.invalid_flag = 1 OR viva_results.issue_flag = 1 OR viva_results.high_mark_review = 1 THEN 0 ELSE 1 END')
            ->orderBy('viva_results.reg')
            ->paginate(100)
            ->withQueryString();

        return view('viva.reviews', compact('rows', 'review', 'quota', 'direction', 'search'));
    }


    public function candidates(Request $request): View
    {
        $this->authorize('viewAny', VivaResult::class);

        $search = trim((string) $request->query('search', ''));
        $attendance = strtolower((string) $request->query('attendance', ''));
        $status = strtolower((string) $request->query('status', ''));
        $review = strtolower((string) $request->query('review', ''));

        $rows = VivaResult::query()
            ->join('registrations as r', 'r.id', '=', 'viva_results.registration_id')
            ->select(
                'viva_results.*',
                'r.name',
                'r.has_ff_quota',
                'r.has_em_quota',
                'r.has_phc_quota'
            )
            ->when($search !== '', fn ($q) => $q->where(fn ($nested) => $nested
                ->where('viva_results.reg', $search)
                ->orWhere('viva_results.user_id', $search)
                ->orWhere('viva_results.code', $search)
                ->orWhere('r.name', 'like', "%{$search}%")))
            ->when(in_array($attendance, ['appeared', 'absent'], true), fn ($q) => $q->where('viva_results.attendance_status', $attendance))
            ->when(in_array($status, ['active', 'cancelled', 'withheld', 'expelled'], true), fn ($q) => $q->where('viva_results.status', $status))
            ->when($review === 'warning', fn ($q) => $q->where(fn ($nested) => $nested
                ->where('viva_results.validation_status', 'warning')
                ->orWhere('viva_results.quota_mismatch', 1)
                ->orWhere('viva_results.invalid_flag', 1)
                ->orWhere('viva_results.issue_flag', 1)
                ->orWhere('viva_results.high_mark_review', 1)))
            ->orderByRaw("CASE WHEN viva_results.validation_status = 'warning' OR viva_results.quota_mismatch = 1 OR viva_results.invalid_flag = 1 OR viva_results.issue_flag = 1 OR viva_results.high_mark_review = 1 THEN 0 ELSE 1 END")
            ->orderBy('viva_results.reg')
            ->paginate(100)
            ->withQueryString();

        return view('viva.candidates', compact('rows', 'search', 'attendance', 'status', 'review'));
    }

    public function editCandidate(VivaResult $result): View
    {
        $this->authorize('process', VivaResult::class);

        $registration = Registration::query()->findOrFail($result->registration_id);
        $audits = VivaProcessingAudit::query()
            ->where('viva_result_id', $result->id)
            ->where('action', 'VIVA_MANUAL_CORRECTION')
            ->latest('id')
            ->limit(25)
            ->get();

        return view('viva.edit', compact('result', 'registration', 'audits'));
    }

    public function updateCandidate(
        Request $request,
        VivaResult $result,
        VivaManualCorrectionService $service,
    ): RedirectResponse {
        $this->authorize('process', VivaResult::class);

        $validated = $request->validate([
            'viva_date' => ['required', 'date_format:Y-m-d'],
            'member_id' => ['required', 'string', 'max:100'],
            'mark' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:active,cancelled,withheld,expelled'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $validated['viva_cff'] = $request->boolean('viva_cff');
        $validated['viva_em'] = $request->boolean('viva_em');
        $validated['viva_phc'] = $request->boolean('viva_phc');
        $validated['invalid_flag'] = $request->boolean('invalid_flag');
        $validated['issue_flag'] = $request->boolean('issue_flag');

        $outcome = $service->update($result, $validated, $request->user());

        if (! $outcome['changed']) {
            return back()->with('info', 'No Viva data changed. No audit entry was created.');
        }

        $message = $outcome['stale']
            ? 'Viva data updated and audited. Reconciliation is now outdated and must be regenerated.'
            : 'Viva comment updated and audited. Processing state remains current.';

        return redirect()
            ->route('viva.candidates.edit', $result)
            ->with('success', $message);
    }


    public function processResults(
        Request $request,
        ExaminationContext $context,
        VivaAuditService $audit,
        VivaRuleConfig $rules,
    ): RedirectResponse {
        $this->authorize('process', VivaResult::class);

        $state = VivaProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        abort_unless($state->reconciliation_generated_at && ! $state->is_stale, 409, 'Generate a current Viva reconciliation before result processing.');
        abort_if(VivaProcessingRun::query()->whereIn('status', ['queued', 'running'])->exists(), 409, 'A Viva result processing run is already active.');
        abort_if(VivaResult::query()->doesntExist(), 409, 'No approved Viva Board data is available for processing.');

        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $version = (int) VivaProcessingRun::query()->max('processing_version') + 1;
        $run = VivaProcessingRun::query()->create([
            'processing_version' => $version,
            'status' => 'queued',
            'full_mark' => $rules->fullMark(),
            'pass_percent' => $rules->passPercent(),
            'pass_mark' => $rules->passMark(),
            'created_by' => $request->user()->id,
            'current_step' => 'Waiting in queue',
        ]);

        $state->update([
            'status' => 'processing_running',
            'latest_processing_run_id' => $run->id,
        ]);

        ProcessVivaResults::dispatch($examinationId, $run->id, $request->user()->id);
        $audit->record(
            'VIVA_RESULT_PROCESSING_QUEUED',
            $request->user(),
            'reconciliation_generated',
            'processing_running',
            summary: [
                'run_id' => $run->id,
                'processing_version' => $version,
                'full_mark' => $rules->fullMark(),
                'pass_percent' => $rules->passPercent(),
                'pass_mark' => $rules->passMark(),
            ],
        );

        return redirect()->route('viva.processing.show', $run)
            ->with('success', 'Viva attendance and PASS/FAIL processing queued.');
    }

    public function processingShow(VivaProcessingRun $run): View
    {
        $this->authorize('viewAny', VivaResult::class);

        return view('viva.processing', ['run' => $run]);
    }

    public function processingStatus(VivaProcessingRun $run): JsonResponse
    {
        $this->authorize('viewAny', VivaResult::class);
        $run->refresh();

        return response()->json([
            'status' => $run->status,
            'total_rows' => (int) $run->total_rows,
            'processed_rows' => (int) $run->processed_rows,
            'progress_percent' => (float) $run->progress_percent,
            'current_step' => $run->current_step,
            'failure_message' => $run->failure_message,
            'finished' => ! in_array($run->status, ['queued', 'running'], true),
            'redirect_url' => route('viva.processing.show', $run),
        ]);
    }

    public function results(Request $request): View
    {
        $this->authorize('viewAny', VivaResult::class);

        $search = trim((string) $request->query('search', ''));
        $resultStatus = strtolower((string) $request->query('result', ''));
        $candidateStatus = strtolower((string) $request->query('status', ''));
        $attendance = strtolower((string) $request->query('attendance', ''));
        $review = strtolower((string) $request->query('review', ''));

        $rows = VivaResult::query()
            ->join('registrations as r', 'r.id', '=', 'viva_results.registration_id')
            ->select('viva_results.*', 'r.name')
            ->when($search !== '', fn ($q) => $q->where(fn ($nested) => $nested
                ->where('viva_results.reg', $search)
                ->orWhere('viva_results.user_id', $search)
                ->orWhere('viva_results.code', $search)
                ->orWhere('r.name', 'like', "%{$search}%")))
            ->when(in_array($resultStatus, ['pass', 'fail', 'not_applicable', 'pending'], true), fn ($q) => $q->where('viva_results.viva_result_status', $resultStatus))
            ->when(in_array($candidateStatus, ['active', 'cancelled', 'withheld', 'expelled'], true), fn ($q) => $q->where('viva_results.status', $candidateStatus))
            ->when(in_array($attendance, ['appeared', 'absent'], true), fn ($q) => $q->where('viva_results.attendance_status', $attendance))
            ->when($review === 'warning', fn ($q) => $q->where(fn ($nested) => $nested
                ->where('viva_results.validation_status', 'warning')
                ->orWhere('viva_results.quota_mismatch', 1)
                ->orWhere('viva_results.invalid_flag', 1)
                ->orWhere('viva_results.issue_flag', 1)
                ->orWhere('viva_results.high_mark_review', 1)))
            ->orderBy('viva_results.reg')
            ->paginate(100)
            ->withQueryString();

        return view('viva.results', compact(
            'rows', 'search', 'resultStatus', 'candidateStatus', 'attendance', 'review'
        ));
    }

    public function exportResults(
        Request $request,
        VivaInternalResultExportService $service,
        VivaAuditService $audit,
        ExaminationContext $context,
    ): BinaryFileResponse {
        $this->authorize('viewAny', VivaResult::class);

        $state = VivaProcessingState::query()->first();
        abort_unless($state?->result_processed_at && ! $state?->is_stale, 409, 'Process a current Viva result before exporting internal reports.');

        $scope = strtolower((string) $request->query('scope', 'all'));
        $output = $service->create(
            $scope,
            (string) $request->user()->name,
            (string) ($context->current()?->name ?? 'Selected Examination'),
        );

        $audit->record(
            'VIVA_INTERNAL_XLSX_EXPORTED',
            $request->user(),
            'processing_completed',
            'processing_completed',
            'Authorized internal Viva report export.',
            summary: ['scope' => $scope, 'rows' => $output['count'], 'filename' => $output['filename']],
        );

        return response()->download($output['path'], $output['filename'])->deleteFileAfterSend();
    }


    public function finalReview():View
    {
        $this->authorize('process',VivaResult::class);
        $state=VivaProcessingState::query()->firstOrCreate(['id'=>1],['status'=>'not_started']);
        $latestProcessingRun=VivaProcessingRun::query()->latest('id')->first();
        $latestFinalizationRun=VivaFinalizationRun::query()->latest('id')->first();
        $summary=[
            'total'=>VivaResult::query()->count(),'active'=>VivaResult::query()->where('status','active')->count(),
            'appeared'=>VivaResult::query()->where('attendance_status','appeared')->count(),'absent'=>VivaResult::query()->where('attendance_status','absent')->count(),
            'pass'=>VivaResult::query()->where('viva_result_status','pass')->count(),'fail'=>VivaResult::query()->where('viva_result_status','fail')->count(),
            'not_applicable'=>VivaResult::query()->where('viva_result_status','not_applicable')->count(),
            'cancelled'=>VivaResult::query()->where('status','cancelled')->count(),'withheld'=>VivaResult::query()->where('status','withheld')->count(),'expelled'=>VivaResult::query()->where('status','expelled')->count(),
            'warnings'=>VivaResult::query()->where(fn($q)=>$q->where('validation_status','warning')->orWhere('quota_mismatch',true)->orWhere('invalid_flag',true)->orWhere('issue_flag',true)->orWhere('high_mark_review',true))->count(),
        ];
        $ready=(bool)$state->result_processed_at && !(bool)$state->is_stale && $latestProcessingRun?->status==='completed' && $summary['total']>0
            && VivaResult::query()->where(fn($q)=>$q->whereNull('processing_run_id')->orWhereNull('viva_result_status'))->doesntExist();
        return view('viva.final-review',compact('state','latestProcessingRun','latestFinalizationRun','summary','ready'));
    }

    public function finalizeResult(Request $request,VivaFinalizationService $service):RedirectResponse
    {
        $this->authorize('process',VivaResult::class);
        $validated=$request->validate(['confirmation'=>['required','string'],'notes'=>['nullable','string','max:5000']]);
        $run=$service->finalize($request->user(),(string)$validated['confirmation'],$validated['notes']??null);
        return redirect()->route('viva.final-review')->with('success',sprintf('Confidential Viva result finalized successfully from processing version %d.',$run->processing_version));
    }

    public function mappingTemplate(VivaTemplateService $service):BinaryFileResponse{$this->authorize('viewAny',VivaResult::class);$d=storage_path('app/private/viva');File::ensureDirectoryExists($d);$p=$d.'/viva-candidate-mapping-template.xlsx';$service->createMappingTemplate($p);return response()->download($p,basename($p))->deleteFileAfterSend();}
    public function boardTemplate(VivaTemplateService $service):BinaryFileResponse{$this->authorize('viewAny',VivaResult::class);$d=storage_path('app/private/viva');File::ensureDirectoryExists($d);$p=$d.'/viva-board-data-template.xlsx';$service->createBoardTemplate($p);return response()->download($p,basename($p))->deleteFileAfterSend();}
}
