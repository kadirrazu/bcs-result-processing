<?php

namespace App\Http\Controllers;

use App\Enums\WrittenCandidateStatus;
use App\Enums\WrittenProcessingStatus;
use App\Jobs\ApproveWrittenImport;
use App\Jobs\ProcessWrittenImport;
use App\Jobs\ProcessWrittenRules;
use App\Jobs\FinalizeWrittenResults;
use App\Jobs\ValidateWrittenImport;
use App\Models\WrittenImportBatch;
use App\Models\WrittenProcessingAudit;
use App\Models\WrittenProcessingRun;
use App\Models\WrittenReconciliationReport;
use App\Models\WrittenProcessingState;
use App\Models\WrittenResult;
use App\Services\Written\WrittenAuditService;
use App\Services\Written\WrittenImportService;
use App\Services\Written\WrittenReconciliationService;
use App\Services\Written\WrittenResultEditService;
use App\Services\Written\WrittenSubjectConfig;
use App\Services\Written\WrittenTemplateService;
use App\Services\MasterData\CodeLabelService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class WrittenController extends Controller
{
    public function index(WrittenSubjectConfig $subjects): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $state = WrittenProcessingState::query()->firstOrCreate(['id' => 1], ['status' => WrittenProcessingStatus::NotStarted->value]);

        return view('written.index', [
            'state' => $state,
            'latestBatch' => WrittenImportBatch::query()->latest('id')->first(),
            'batches' => WrittenImportBatch::query()->latest('id')->paginate(15),
            'audits' => WrittenProcessingAudit::query()->latest('id')->limit(10)->get(),
            'latestReconciliation' => $state->latest_reconciliation_report_id
                ? WrittenReconciliationReport::query()->find($state->latest_reconciliation_report_id)
                : null,
            'latestProcessingRun' => $state->latest_processing_run_id
                ? WrittenProcessingRun::query()->find($state->latest_processing_run_id)
                : ($state->is_stale ? null : WrittenProcessingRun::query()->latest('id')->first()),
            'counts' => [
                'results' => WrittenResult::query()->count(),
                'warnings' => WrittenResult::query()->where('validation_status', 'warning')->count(),
                'active' => WrittenResult::query()->where('status', 'active')->count(),
                'cancelled' => WrittenResult::query()->where('status', 'cancelled')->count(),
                'withheld' => WrittenResult::query()->where('status', 'withheld')->count(),
                'expelled' => WrittenResult::query()->where('status', 'expelled')->count(),
            ],
            'ruleSummary' => [
                'general_full_mark' => $subjects->trackFullMark('general'),
                'technical_full_mark' => $subjects->trackFullMark('technical'),
                'general_pass_mark' => $subjects->trackPassThreshold('general'),
                'technical_pass_mark' => $subjects->trackPassThreshold('technical'),
                'paper_crash_percent' => (float) config('written.paper_crash_percent'),
                'high_mark_review_percent' => (float) config('written.high_mark_review_percent'),
            ],
        ]);
    }

    public function store(Request $request, WrittenImportService $service, WrittenAuditService $audit, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:102400']]);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $batch = $service->enqueue($validated['file'], $request->user()->id, $examinationId);
        $audit->record('WRITTEN_IMPORT_QUEUED', $request->user(), null, 'queued', null, summary: ['original_name' => $batch->original_name], batchId: $batch->id);

        return redirect()->route('written.import.result', $batch)->with('success', 'Written mark file queued for fast staging.');
    }

    public function template(WrittenTemplateService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        $directory = storage_path('app/private/written');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/written-import-template.xlsx';
        $service->create($path);
        return response()->download($path, 'written-import-template.xlsx')->deleteFileAfterSend();
    }

    public function result(Request $request, WrittenImportBatch $batch, WrittenSubjectConfig $subjects): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $validation = trim((string) $request->query('validation', 'all'));
        $highMark = trim((string) $request->query('high_mark', ''));
        $search = trim((string) $request->query('search', ''));

        $query = $batch->stagingRows()
            ->when($validation !== '' && $validation !== 'all', fn ($q) => $q->where('validation_status', $validation))
            ->when($highMark !== '', function ($q) use ($highMark): void {
                $needle = $highMark === 'any' ? 'HIGH_MARK_REVIEW:' : 'HIGH_MARK_REVIEW:'.$highMark.':';
                $q->where('validation_warnings', 'like', '%'.$needle.'%');
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(fn ($nested) => $nested->where('reg', $search)->orWhere('user_id', $search));
            })
            ->orderByRaw("CASE validation_status WHEN 'warning' THEN 0 WHEN 'identity_conflict' THEN 1 WHEN 'invalid' THEN 2 WHEN 'valid' THEN 3 ELSE 4 END")
            ->orderBy('source_row');

        return view('written.import-result', [
            'record' => $batch,
            'rows' => $query->paginate(100)->withQueryString(),
            'filters' => compact('validation', 'highMark', 'search'),
            'highMarkSubjects' => [...array_keys(array_filter($subjects->subjects(), fn ($v, $k) => $k !== '008' && $k !== '009', ARRAY_FILTER_USE_BOTH)), '008_009'],
        ]);
    }

    public function retryStaging(WrittenImportBatch $batch, ExaminationContext $context, Request $request, WrittenAuditService $audit): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        abort_unless($batch->status === 'failed' && (int) $batch->approved_rows === 0, 409, 'Only a failed, unapproved Written import can retry staging.');
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');
        $before = $batch->status;
        $batch->update(['status'=>'queued','failure_message'=>null,'processed_rows'=>0,'staged_rows'=>0,'valid_rows'=>0,'warning_rows'=>0,'invalid_rows'=>0,'identity_conflict_rows'=>0,'progress_percent'=>0,'finished_at'=>null]);
        ProcessWrittenImport::dispatch($examinationId, $batch->id, $request->user()->id);
        $audit->record('WRITTEN_IMPORT_RETRY_QUEUED', $request->user(), $before, 'queued', 'Retry staging after a failed import.', batchId: $batch->id);
        return back()->with('success', 'Written staging retry queued.');
    }

    public function validateBatch(WrittenImportBatch $batch, ExaminationContext $context, Request $request, WrittenAuditService $audit): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        abort_unless(in_array($batch->status, ['staged', 'validated', 'failed'], true) && (int) $batch->approved_rows === 0 && (int) $batch->staged_rows > 0, 409, 'Validation requires successfully staged Written rows. Retry staging first.');
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $before = $batch->status;
        $batch->update(['status' => 'validation_queued', 'progress_percent' => 0, 'failure_message' => null]);
        ValidateWrittenImport::dispatch($examinationId, $batch->id, $request->user()->id);
        $audit->record('WRITTEN_VALIDATION_QUEUED', $request->user(), $before, 'validation_queued', null, batchId: $batch->id);
        return back()->with('success', 'Written validation queued.');
    }

    public function approve(WrittenImportBatch $batch, ExaminationContext $context, Request $request, WrittenAuditService $audit): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        abort_unless(
            $batch->status === 'validated' || ($batch->status === 'failed' && (int) $batch->approved_rows === 0 && ((int) $batch->valid_rows + (int) $batch->warning_rows) > 0),
            409,
            'Only validated Written data can be approved.',
        );
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $before = $batch->status;
        $batch->update(['status' => 'approval_queued', 'progress_percent' => 0, 'failure_message' => null]);
        ApproveWrittenImport::dispatch($examinationId, $batch->id, $request->user()->id);
        $audit->record('WRITTEN_APPROVAL_QUEUED', $request->user(), $before, 'approval_queued', null, batchId: $batch->id);
        return back()->with('success', 'Valid and warning Written rows queued for approval/merge.');
    }

    public function status(WrittenImportBatch $batch): JsonResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        $batch->refresh();
        return response()->json([
            'id' => $batch->id, 'status' => $batch->status,
            'total_rows' => (int) $batch->total_rows, 'processed_rows' => (int) $batch->processed_rows,
            'staged_rows' => (int) $batch->staged_rows, 'valid_rows' => (int) $batch->valid_rows,
            'warning_rows' => (int) $batch->warning_rows, 'invalid_rows' => (int) $batch->invalid_rows,
            'identity_conflict_rows' => (int) $batch->identity_conflict_rows, 'approved_rows' => (int) $batch->approved_rows,
            'inserted_rows' => (int) $batch->inserted_rows, 'updated_rows' => (int) $batch->updated_rows,
            'progress_percent' => (float) $batch->progress_percent, 'failure_message' => $batch->failure_message,
            'finished' => ! in_array($batch->status, ['queued', 'staging', 'validation_queued', 'validating', 'approval_queued', 'approving'], true),
        ]);
    }

    public function report(WrittenImportBatch $batch): BinaryFileResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        set_time_limit(0);
        $directory = storage_path('app/private/written-import-reports');
        File::ensureDirectoryExists($directory);
        $filename = "written-import-batch-{$batch->id}-issues.csv";
        $path = $directory.DIRECTORY_SEPARATOR.uniqid("batch-{$batch->id}-", true).'.csv';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("\xEF\xBB\xBF");
        $file->fputcsv(['source_row', 'reg', 'user', 'prs_code', 'data_source_note', 'validation_status', 'warnings', 'errors']);

        DB::connection('exam')->table('written_import_staging')
            ->select(['id', 'source_row', 'reg', 'user_id', 'prs_code', 'data_source_note', 'validation_status', 'validation_warnings', 'validation_errors'])
            ->where('batch_id', $batch->id)->whereIn('validation_status', ['warning', 'invalid', 'identity_conflict'])
            ->orderBy('id')->chunkById(3000, function ($rows) use ($file): void {
                foreach ($rows as $row) {
                    $file->fputcsv([$row->source_row, $row->reg, $row->user_id, $row->prs_code, $row->data_source_note, $row->validation_status, $this->csvMessages($row->validation_warnings), $this->csvMessages($row->validation_errors)]);
                }
            }, 'id');
        unset($file);

        return response()->download($path, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, no-cache, must-revalidate'])->deleteFileAfterSend(true);
    }

    public function generateReconciliation(Request $request, WrittenReconciliationService $service, WrittenAuditService $audit): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        $report = $service->generate((int) $request->user()->id);
        $audit->record(
            'WRITTEN_RECONCILIATION_GENERATED',
            $request->user(),
            'marks_imported',
            'reconciliation_generated',
            'Generate Eligible / Appeared / Absent reconciliation.',
            summary: (array) $report->summary,
        );

        return redirect()->route('written.reconciliation')->with('success', 'Written reconciliation generated.');
    }

    public function reconciliation(): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $state = WrittenProcessingState::query()->firstOrCreate(['id' => 1], ['status' => WrittenProcessingStatus::NotStarted->value]);
        $report = $state->latest_reconciliation_report_id
            ? WrittenReconciliationReport::query()->find($state->latest_reconciliation_report_id)
            : WrittenReconciliationReport::query()->latest('id')->first();

        return view('written.reconciliation', ['state' => $state, 'report' => $report]);
    }

    public function processRules(Request $request, ExaminationContext $context, WrittenAuditService $audit): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        $state = WrittenProcessingState::query()->firstOrCreate(['id' => 1], ['status' => WrittenProcessingStatus::NotStarted->value]);
        abort_if($state->reconciliation_generated_at === null || $state->is_stale, 409, 'Generate a current Written reconciliation first.');
        abort_if(WrittenProcessingRun::query()->whereIn('status', ['queued', 'running'])->exists(), 409, 'A Written rule-processing run is already active.');
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $run = WrittenProcessingRun::query()->create([
            'type' => 'written_rules', 'status' => 'queued', 'total_rows' => WrittenResult::query()->count(),
            'processed_rows' => 0, 'progress_percent' => 0, 'current_step' => 'Waiting for queue worker',
            'created_by' => (int) $request->user()->id,
        ]);
        $state->update(['latest_processing_run_id' => $run->id, 'paper_crash_processed_at' => null, 'paper_crash_processed_by' => null, 'result_finalized_at' => null, 'result_finalized_by' => null]);
        ProcessWrittenRules::dispatch($examinationId, $run->id, (int) $request->user()->id);
        $audit->record('WRITTEN_RULE_PROCESSING_QUEUED', $request->user(), 'reconciliation_generated', 'queued', null, processingRunId: $run->id);

        return back()->with('success', 'Written paper-crash and track processing queued.');
    }

    public function processingRunStatus(WrittenProcessingRun $run): JsonResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        $run->refresh();
        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'total_rows' => (int) $run->total_rows,
            'processed_rows' => (int) $run->processed_rows,
            'progress_percent' => (float) $run->progress_percent,
            'current_step' => $run->current_step,
            'failure_message' => $run->failure_message,
            'status_label' => \App\Support\WrittenStatusPresenter::label($run->status),
            'task_label' => \App\Support\WrittenStatusPresenter::taskLabel($run->type),
            'finished' => in_array($run->status, ['completed', 'failed'], true),
        ]);
    }

    public function finalize(Request $request, ExaminationContext $context, WrittenAuditService $audit): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $state = WrittenProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => WrittenProcessingStatus::NotStarted->value],
        );
        $stateValue = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;

        abort_if((bool) $state->is_stale, 409, 'Written data changed after processing. Regenerate reconciliation and process Written rules before finalizing.');
        abort_if($state->reconciliation_generated_at === null, 409, 'Generate the current Written reconciliation before finalizing.');
        abort_if($state->paper_crash_processed_at === null, 409, 'Process Written rules before finalizing the result.');
        abort_unless(in_array($stateValue, [WrittenProcessingStatus::ProcessingReady->value, WrittenProcessingStatus::ResultFinalized->value], true), 409, 'The Written result is not ready for final review yet.');
        abort_if(WrittenProcessingRun::query()->whereIn('status', ['queued', 'running'])->exists(), 409, 'Another Written background task is already in progress.');

        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $run = WrittenProcessingRun::query()->create([
            'type' => 'written_finalization',
            'status' => 'queued',
            'total_rows' => WrittenResult::query()->count(),
            'processed_rows' => 0,
            'progress_percent' => 0,
            'current_step' => 'Waiting to start final review',
            'created_by' => (int) $request->user()->id,
        ]);

        $state->update(['latest_processing_run_id' => $run->id]);
        FinalizeWrittenResults::dispatch(
            $examinationId,
            $run->id,
            (int) $request->user()->id,
            $validated['reason'],
        );

        $audit->record(
            'WRITTEN_RESULT_FINALIZATION_QUEUED',
            $request->user(),
            $stateValue,
            'queued',
            $validated['reason'],
            summary: ['run_id' => $run->id, 'candidate_rows' => (int) $run->total_rows],
            batchId: $state->latest_import_batch_id,
            processingRunId: $run->id,
        );

        return back()->with('success', 'Final review has been queued. The progress panel will update automatically.');
    }

    public function finalResultCombined(): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $state = $this->finalizedState();

        return view('written.final-result-combined', [
            'state' => $state,
            'registrations' => $this->qualifiedRegistrations(),
        ]);
    }

    public function finalResultCategory(): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $state = $this->finalizedState();

        return view('written.final-result-category', [
            'state' => $state,
            'groups' => [
                'GG' => $this->qualifiedRegistrations(['GG', 'GN']),
                'TT' => $this->qualifiedRegistrations(['TT', 'T']),
                'GT' => $this->qualifiedRegistrations(['GT']),
            ],
        ]);
    }

    public function finalResultCombinedTxt(): BinaryFileResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        $this->finalizedState();
        $regs = $this->qualifiedRegistrations();
        $directory = storage_path('app/private/written-final-results');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.uniqid('written-result-combined-', true).'.txt';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("WRITTEN EXAMINATION RESULT - ALL QUALIFIED CANDIDATES\r\n\r\n");
        foreach ($regs->chunk(10) as $row) {
            $file->fwrite($row->implode('    ')."\r\n");
        }
        $file->fwrite("\r\nTOTAL = ".number_format($regs->count())."\r\n");
        unset($file);

        return response()->download($path, 'written-result-combined.txt', ['Content-Type' => 'text/plain; charset=UTF-8'])->deleteFileAfterSend(true);
    }

    public function finalResultCategoryTxt(): BinaryFileResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        $this->finalizedState();
        $groups = [
            'GG' => $this->qualifiedRegistrations(['GG', 'GN']),
            'TT' => $this->qualifiedRegistrations(['TT', 'T']),
            'GT' => $this->qualifiedRegistrations(['GT']),
        ];
        $directory = storage_path('app/private/written-final-results');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.uniqid('written-result-category-', true).'.txt';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("WRITTEN EXAMINATION RESULT - CATEGORY WISE\r\n");
        foreach ($groups as $category => $regs) {
            $file->fwrite("\r\n{$category}\r\n".str_repeat('-', 40)."\r\n");
            foreach ($regs->chunk(10) as $row) {
                $file->fwrite($row->implode('    ')."\r\n");
            }
            $file->fwrite("\r\nTOTAL ({$category}) = ".number_format($regs->count())."\r\n");
        }
        unset($file);

        return response()->download($path, 'written-result-category-wise.txt', ['Content-Type' => 'text/plain; charset=UTF-8'])->deleteFileAfterSend(true);
    }

    public function failureReasons(Request $request): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $this->finalizedState();
        $search = trim((string) $request->query('search', ''));
        $scope = trim((string) $request->query('scope', 'all'));

        $query = WrittenResult::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('general_result_status', 'fail')->orWhere('technical_result_status', 'fail');
            })
            ->when($scope === 'fully_failed', fn ($q) => $q->whereNull('written_qualified_track'))
            ->when($scope === 'partially_qualified', fn ($q) => $q->whereIn('written_qualified_track', ['GN', 'T']))
            ->when($search !== '', fn ($q) => $q->where(fn ($nested) => $nested->where('reg', $search)->orWhere('user_id', $search)))
            ->orderByRaw('CAST(reg AS UNSIGNED) ASC');

        return view('written.failure-reasons', [
            'rows' => $query->paginate(100)->withQueryString(),
            'search' => $search,
            'scope' => $scope,
        ]);
    }

    public function paperCrashes(Request $request): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $subject = trim((string) $request->query('subject', 'all'));
        $search = trim((string) $request->query('search', ''));
        $query = DB::connection('exam')->table('written_candidate_marks as m')
            ->join('written_results as w', 'w.id', '=', 'm.written_result_id')
            ->where('m.paper_crashed', 1)
            ->when($subject !== '' && $subject !== 'all', fn ($q) => $q->where('m.subject_code', $subject))
            ->when($search !== '', fn ($q) => $q->where(fn ($n) => $n->where('w.reg', $search)->orWhere('w.user_id', $search)))
            ->select(['m.id', 'm.subject_code', 'm.actual_mark', 'm.counted_mark', 'm.crash_threshold', 'w.reg', 'w.user_id', 'w.cadre_category', 'w.written_qualified_track'])
            ->orderBy('w.reg')->orderBy('m.subject_code');

        return view('written.paper-crashes', [
            'rows' => $query->paginate(100)->withQueryString(),
            'filters' => compact('subject', 'search'),
            'subjects' => array_keys((array) config('written.subjects', [])),
        ]);
    }

    public function results(Request $request): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $validation = trim((string) $request->query('validation', 'all'));
        $status = trim((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));
        $highMark = $request->boolean('high_mark');

        $query = WrittenResult::query()->with(['marks' => fn ($q) => $q->orderBy('id')])
            ->when($validation !== 'all' && $validation !== '', fn ($q) => $q->where('validation_status', $validation))
            ->when(in_array($status, ['active', 'cancelled', 'withheld', 'expelled'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($nested) => $nested->where('reg', $search)->orWhere('user_id', $search)))
            ->when($highMark, fn ($q) => $q->whereHas('marks', fn ($marks) => $marks->where('warning_codes', 'like', '%HIGH_MARK_REVIEW:%')))
            ->orderByRaw("CASE validation_status WHEN 'warning' THEN 0 WHEN 'valid' THEN 1 ELSE 2 END")
            ->orderBy('reg');

        return view('written.results', [
            'rows' => $query->paginate(100)->withQueryString(),
            'filters' => compact('validation', 'status', 'search', 'highMark'),
            'state' => WrittenProcessingState::query()->firstOrCreate(
                ['id' => 1],
                ['status' => WrittenProcessingStatus::NotStarted->value],
            ),
        ]);
    }

    public function show(WrittenResult $result, CodeLabelService $labels): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $result->load(['marks' => fn ($q) => $q->orderBy('id')]);
        $registration = DB::connection('exam')->table('registrations')
            ->where('id', $result->registration_id)
            ->first(['name', 'cadre_category', 'post_related_subject_code', 'district_code', 'division_code', 'university_code', 'bachelor_subject_code', 'sex_code']);
        $audits = WrittenProcessingAudit::query()
            ->where('written_result_id', $result->id)
            ->latest('id')
            ->limit(50)
            ->get();
        $state = WrittenProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => WrittenProcessingStatus::NotStarted->value],
        );

        $display = $this->writtenReferenceDisplay($result, $registration, $labels);

        return view('written.show', compact('result', 'registration', 'audits', 'state', 'display'));
    }

    public function edit(WrittenResult $result, CodeLabelService $labels): View
    {
        $this->authorize('process', WrittenResult::class);
        $result->load(['marks' => fn ($q) => $q->orderBy('id')]);
        $registration = DB::connection('exam')->table('registrations')
            ->where('id', $result->registration_id)
            ->first(['name', 'cadre_category', 'post_related_subject_code', 'district_code', 'division_code', 'university_code', 'bachelor_subject_code', 'sex_code']);
        $audits = WrittenProcessingAudit::query()
            ->where('written_result_id', $result->id)
            ->latest('id')
            ->limit(25)
            ->get();

        $display = $this->writtenReferenceDisplay($result, $registration, $labels);

        return view('written.edit', [
            'result' => $result,
            'registration' => $registration,
            'display' => $display,
            'audits' => $audits,
            'subjectConfig' => (array) config('written.subjects', []),
            'statusOptions' => array_map(
                static fn (WrittenCandidateStatus $status): string => $status->value,
                WrittenCandidateStatus::cases(),
            ),
        ]);
    }

    public function update(Request $request, WrittenResult $result, WrittenResultEditService $service): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        $subjectCodes = array_keys((array) config('written.subjects', []));
        $rules = [
            'prs_code' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:active,cancelled,withheld,expelled'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'marks' => ['required', 'array'],
        ];
        foreach ($subjectCodes as $subjectCode) {
            $rules['marks.'.$subjectCode] = ['nullable', 'string', 'max:100'];
        }
        $validated = $request->validate($rules);

        $outcome = $service->update(
            $result,
            (array) $validated['marks'],
            $validated['prs_code'] ?? null,
            $validated['status'],
            $validated['comment'] ?? null,
            $validated['reason'],
            $request->user(),
        );

        if (! $outcome['changed']) {
            return redirect()->route('written.results.edit', $result)
                ->with('success', 'No Written values changed; no audit event or stale state was created.');
        }

        $message = $outcome['stale']
            ? 'Written facts updated with audit trail. Reconciliation and rule-processing results are now stale; regenerate the required steps.'
            : 'Written comment updated with audit trail. Result-processing state remains current because no result-affecting fact changed.';

        return redirect()->route('written.results.edit', $result)->with('success', $message);
    }

    /** @return array<string, mixed> */
    private function writtenReferenceDisplay(WrittenResult $result, ?object $registration, CodeLabelService $labels): array
    {
        $registrationPrs = $registration?->post_related_subject_code;
        $writtenPrs = $result->prs_code;
        $normalize = static fn (mixed $value): ?string => ($value === null || trim((string) $value) === '')
            ? null
            : trim((string) $value);

        return [
            'cadre_category' => $labels->cadreCategory($registration?->cadre_category ?? $result->cadre_category),
            'registration_prs' => $labels->postRelatedSubject($registrationPrs),
            'written_prs' => $labels->postRelatedSubject($writtenPrs),
            'prs_mismatch' => $normalize($registrationPrs) !== null
                && $normalize($writtenPrs) !== null
                && $normalize($registrationPrs) !== $normalize($writtenPrs),
            'district' => $labels->district($registration?->district_code),
            'division' => $labels->division($registration?->division_code),
            'university' => $labels->university($registration?->university_code),
            'bachelor_subject' => $labels->bachelorSubject($registration?->bachelor_subject_code),
            'gender' => $labels->gender($registration?->sex_code),
        ];
    }

    private function finalizedState(): WrittenProcessingState
    {
        $state = WrittenProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => WrittenProcessingStatus::NotStarted->value],
        );
        abort_if($state->result_finalized_at === null, 409, 'The Written result has not been finalized yet.');
        abort_if((bool) $state->is_stale, 409, 'The Written result is out of date after a correction. Reprocess and finalize it again.');

        return $state;
    }

    /** @param list<string>|null $tracks */
    private function qualifiedRegistrations(?array $tracks = null)
    {
        return DB::connection('exam')->table('written_results')
            ->where('status', 'active')
            ->whereNotNull('written_qualified_track')
            ->when($tracks !== null, fn ($query) => $query->whereIn('written_qualified_track', $tracks))
            ->orderByRaw('CAST(reg AS UNSIGNED) ASC')
            ->pluck('reg');
    }

    private function csvMessages(mixed $value): string
    {
        if ($value === null || $value === '') { return ''; }
        if (is_array($value)) { return implode(' | ', array_map('strval', $value)); }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? implode(' | ', array_map('strval', $decoded)) : (string) $value;
    }
}
