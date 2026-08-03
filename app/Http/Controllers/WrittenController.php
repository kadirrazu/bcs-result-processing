<?php

namespace App\Http\Controllers;

use App\Enums\WrittenCandidateStatus;
use App\Enums\WrittenProcessingStatus;
use App\Jobs\ApproveWrittenImport;
use App\Jobs\RevalidateAndMergeCorrectedImportRows;
use App\Jobs\ProcessWrittenImport;
use App\Jobs\ProcessWrittenRules;
use App\Jobs\FinalizeWrittenResults;
use App\Jobs\ValidateWrittenImport;
use App\Models\ImportCorrectionEntry;
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
use App\Services\Imports\InvalidRowCorrectionService;
use App\Services\Documents\DocxPlaceholderTemplateService;
use App\Services\Exports\AdministrativeXlsxExportService;
use App\Services\Exports\AdministrativeExportCacheService;
use App\Support\Examinations\ExaminationContext;
use App\Support\WrittenResultPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'paper_crash' => DB::connection('exam')->table('written_candidate_marks')->where('paper_crashed', 1)->distinct()->count('written_result_id'),
                'high_mark' => DB::connection('exam')->table('written_candidate_marks')->where('warning_codes', 'like', '%HIGH_MARK_REVIEW:%')->distinct()->count('written_result_id'),
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
            'corrections' => ImportCorrectionEntry::query()->where('module', 'written')->where('batch_id', $batch->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function correctionTemplate(WrittenImportBatch $batch, InvalidRowCorrectionService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        $directory = storage_path('app/private/import-corrections');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/written-batch-'.$batch->id.'-invalid-rows.xlsx';
        $count = $service->createCorrectionWorkbook('written', (int) $batch->id, $path);
        abort_if($count === 0, 409, 'This batch has no invalid rows to correct.');

        return response()->download($path, 'written-batch-'.$batch->id.'-invalid-rows.xlsx')->deleteFileAfterSend();
    }

    public function applyCorrections(Request $request, WrittenImportBatch $batch, InvalidRowCorrectionService $service, ExaminationContext $context, WrittenAuditService $audit): RedirectResponse
    {
        $this->authorize('process', WrittenResult::class);
        $validated = $request->validate([
            'correction_file' => ['required', 'file', 'mimes:xlsx,csv', 'max:102400'],
        ]);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $before = (string) $batch->status;
        $wasApproved = (int) $batch->approved_rows > 0 || $before === 'approved';
        $summary = $service->apply('written', $batch, $validated['correction_file'], $request->user());
        $batch->update([
            'status' => 'validation_queued',
            'progress_percent' => 0,
            'failure_message' => null,
            'finished_at' => null,
        ]);
        if ($wasApproved) {
            RevalidateAndMergeCorrectedImportRows::dispatch(
                $examinationId, 'written', (int) $batch->id, $summary['source_rows'], (int) $request->user()->id
            );
        } else {
            ValidateWrittenImport::dispatch($examinationId, $batch->id, (int) $request->user()->id);
        }
        $audit->record(
            'WRITTEN_INVALID_ROWS_CORRECTED',
            $request->user(),
            $before,
            'validation_queued',
            'Corrected invalid source rows before approval.',
            summary: ['corrected_rows' => $summary['corrected_rows'], 'source_rows' => $summary['source_rows']],
            batchId: (int) $batch->id,
        );

        return back()->with('success', number_format($summary['corrected_rows']).' invalid Written row(s) were replaced from the correction file. Validation is running again now.');
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

    public function finalResultTemplate(ExaminationContext $context): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $state = $this->finalizedState();

        return view('written.fill-result-template', [
            'state' => $state,
            'examination' => $context->current(),
            'defaultPerLine' => max(1, min(12, (int) config('result-documents.registrations_per_line', 8))),
        ]);
    }

    public function generateFinalResultTemplate(
        Request $request,
        ExaminationContext $context,
        DocxPlaceholderTemplateService $documents,
        WrittenAuditService $audit,
    ): BinaryFileResponse {
        $this->authorize('viewAny', WrittenResult::class);
        $state = $this->finalizedState();

        $validated = $request->validate([
            'template_file' => [
                'required',
                'file',
                'mimes:docx',
                'max:'.max(1024, (int) config('result-documents.max_template_size_kb', 20480)),
            ],
            'registrations_per_line' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $perLine = (int) ($validated['registrations_per_line'] ?? config('result-documents.registrations_per_line', 8));
        $perLine = max(1, min(12, $perLine));
        $separator = (string) config('result-documents.registration_separator', '    ');

        $all = $this->qualifiedRegistrations();
        $groups = [
            'GG' => $this->qualifiedRegistrations(['GG', 'GN']),
            'TT' => $this->qualifiedRegistrations(['TT', 'T']),
            'GT' => $this->qualifiedRegistrations(['GT']),
        ];

        $resultOnly = fn ($regs): string => $this->registrationLines($regs, $perLine, $separator);
        $block = fn ($regs, string $label): string => $this->registrationResultBlock($regs, $label, $perLine, $separator);
        $examination = $context->current();

        $replacements = [
            'RESULT_ALL' => $resultOnly($all),
            'RESULT_GG' => $resultOnly($groups['GG']),
            'RESULT_TT' => $resultOnly($groups['TT']),
            'RESULT_GT' => $resultOnly($groups['GT']),
            'TOTAL_ALL' => number_format($all->count()),
            'TOTAL_GG' => number_format($groups['GG']->count()),
            'TOTAL_TT' => number_format($groups['TT']->count()),
            'TOTAL_GT' => number_format($groups['GT']->count()),
            'ALL' => $block($all, 'ALL'),
            'GG' => $block($groups['GG'], 'GG'),
            'TT' => $block($groups['TT'], 'TT'),
            'GT' => $block($groups['GT'], 'GT'),
            'EXAM_NAME' => (string) ($examination?->name ?? 'Selected Examination'),
            'FINALIZED_DATE' => $state->result_finalized_at?->format('d-m-Y') ?? '',
        ];

        $directory = storage_path('app/private/written-publishing-documents');
        File::ensureDirectoryExists($directory);
        $examSlug = Str::slug((string) ($examination?->name ?? 'written-examination')) ?: 'written-examination';
        $outputName = $examSlug.'-written-result-'.now()->format('Ymd-His').'.docx';
        $outputPath = $directory.DIRECTORY_SEPARATOR.uniqid('publishing-', true).'.docx';

        try {
            $summary = $documents->fill($validated['template_file']->getRealPath(), $outputPath, $replacements);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'The Word template could not be filled. Please check that it is a valid .docx file and try again.');
        }

        $audit->record(
            'WRITTEN_PUBLISHING_DOCUMENT_CREATED',
            $request->user(),
            'finalized',
            'finalized',
            'Created a Word publishing document from the finalized Written result.',
            summary: [
                'template_name' => $validated['template_file']->getClientOriginalName(),
                'output_name' => $outputName,
                'registrations_per_line' => $perLine,
                'placeholder_replacements' => $summary['replaced'],
                'total_replacements' => $summary['total_replacements'],
                'unrecognized_placeholders' => $summary['unknown_placeholders'],
                'finalized_at' => $state->result_finalized_at?->toIso8601String(),
                'cache_hit' => false,
            ],
            batchId: $state->latest_import_batch_id,
        );

        return response()->download(
            $outputPath,
            $outputName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
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

        return view('written.paper-crashes', [
            'rows' => $this->paperCrashQuery($subject, $search)->paginate(100)->withQueryString(),
            'filters' => compact('subject', 'search'),
            'subjects' => $this->reviewSubjectOptions(),
            'statistics' => $this->paperCrashStatistics(),
            'uniqueCandidates' => DB::connection('exam')->table('written_candidate_marks as m')
                ->join('written_results as w', 'w.id', '=', 'm.written_result_id')
                ->where('w.status', 'active')->where('m.paper_crashed', 1)
                ->distinct()->count('m.written_result_id'),
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

    public function administrativeExportXlsx(
        Request $request,
        ExaminationContext $context,
        AdministrativeXlsxExportService $exports,
        AdministrativeExportCacheService $exportCache,
        WrittenAuditService $audit,
    ): BinaryFileResponse {
        $this->authorize('viewAny', WrittenResult::class);
        $state = $this->finalizedState();

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:qualified,all'],
            'order' => ['nullable', 'string', 'in:reg,general_total,technical_total'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);
        $scope = $validated['scope'];
        $order = $validated['order'] ?? 'reg';
        $direction = $validated['direction'] ?? ($order === 'reg' ? 'asc' : 'desc');
        $examination = $context->current();
        $examinationKey = (string) ($examination?->id ?? config('database.connections.exam.database', 'exam'));
        $filename = 'written-'.($scope === 'qualified' ? 'qualified-candidates' : 'all-records').'-'.$state->result_finalized_at->format('Ymd-His').'.xlsx';
        $path = $exportCache->path('written', $examinationKey, $state->result_finalized_at, $scope, $order, $direction);

        if ($exportCache->isReady($path)) {
            $audit->record(
                'WRITTEN_ADMINISTRATIVE_XLSX_EXPORTED',
                $request->user(),
                'result_finalized',
                'result_finalized',
                'Downloaded a previously prepared administrative Excel export from the finalized Written result.',
                summary: [
                    'scope' => $scope, 'order' => $order, 'direction' => $direction,
                    'filename' => $filename, 'cache_hit' => true,
                    'finalized_at' => $state->result_finalized_at?->toIso8601String(),
                ],
                batchId: $state->latest_import_batch_id,
            );

            return response()->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        $subjectCodes = array_keys((array) config('written.subjects', []));
        $selects = [];
        foreach ($subjectCodes as $code) {
            $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $code);
            $selects[] = "MAX(CASE WHEN subject_code = '".str_replace("'", "''", $code)."' THEN m.actual_mark END) AS mark_{$safe}";
        }

        $marksPivot = DB::connection('exam')->table('written_candidate_marks as m')
            ->select('m.written_result_id')
            ->selectRaw(implode(', ', $selects))
            ->groupBy('m.written_result_id');

        $query = DB::connection('exam')->table('written_results as w')
            ->join('registrations as r', 'r.id', '=', 'w.registration_id')
            ->leftJoinSub($marksPivot, 'pm', 'pm.written_result_id', '=', 'w.id')
            ->where('r.status', 'active')
            ->when($scope === 'qualified', fn ($q) => $q->where('w.status', 'active')->whereNotNull('w.written_qualified_track'))
            ->select([
                'w.id', 'w.user_id', 'w.reg', 'r.name', 'w.cadre_category', 'w.prs_code', 'w.status',
                'w.validation_status', 'w.written_qualified_track', 'w.general_actual_total', 'w.general_counted_total',
                'w.technical_actual_total', 'w.technical_counted_total', 'w.general_result_status',
                'w.technical_result_status', 'w.general_fail_reasons', 'w.technical_fail_reasons', 'w.data_source_note',
            ]);
        foreach ($subjectCodes as $code) {
            $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $code);
            $query->addSelect('pm.mark_'.$safe);
        }

        match ($order) {
            'general_total' => $query->orderBy('w.general_counted_total', $direction)->orderBy('w.reg', 'asc'),
            'technical_total' => $query->orderBy('w.technical_counted_total', $direction)->orderBy('w.reg', 'asc'),
            default => $query->orderBy('w.reg', $direction),
        };

        $category = static fn (mixed $value): string => match ((int) $value) {
            1 => '1 - GG', 2 => '2 - TT', 3 => '3 - GT', default => (string) $value,
        };
        $enum = static fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value;

        $headers = ['User ID', 'Registration Number', 'Name', 'Original Category'];
        foreach ($subjectCodes as $code) {
            $headers[] = $code === 'PRS' ? 'PRS Mark' : 'Subject '.$code.' Mark';
        }
        array_push(
            $headers,
            'PRS Code', 'Processing Status', 'Validation Status', 'Qualified Track', 'Effective Category',
            'General Actual Total', 'General Counted Total', 'Technical Actual Total', 'Technical Counted Total',
            'General Result', 'Technical Result', 'Fail Reasons', 'Source Note'
        );

        $rows = (function () use ($query, $subjectCodes, $category, $enum): \Generator {
            foreach ($query->cursor() as $row) {
                $track = (string) ($enum($row->written_qualified_track) ?? '');
                $generalReasons = WrittenResultPresenter::reasons($this->decodeArray($row->general_fail_reasons));
                $technicalReasons = WrittenResultPresenter::reasons($this->decodeArray($row->technical_fail_reasons));
                $data = [
                    (string) $row->user_id,
                    (string) $row->reg,
                    (string) ($row->name ?? ''),
                    $category($row->cadre_category),
                ];
                foreach ($subjectCodes as $code) {
                    $safe = preg_replace('/[^A-Za-z0-9_]/', '_', $code);
                    $property = 'mark_'.$safe;
                    $data[] = $row->{$property} === null ? '' : (float) $row->{$property};
                }
                array_push(
                    $data,
                    (string) ($row->prs_code ?? ''),
                    ucfirst((string) ($enum($row->status) ?? '')),
                    strtoupper((string) ($enum($row->validation_status) ?? '')),
                    $track,
                    WrittenResultPresenter::effectiveCategory($track),
                    $row->general_actual_total === null ? '' : (float) $row->general_actual_total,
                    $row->general_counted_total === null ? '' : (float) $row->general_counted_total,
                    $row->technical_actual_total === null ? '' : (float) $row->technical_actual_total,
                    $row->technical_counted_total === null ? '' : (float) $row->technical_counted_total,
                    strtoupper((string) ($enum($row->general_result_status) ?? '')),
                    strtoupper((string) ($enum($row->technical_result_status) ?? '')),
                    implode(' | ', array_values(array_unique([...$generalReasons, ...$technicalReasons]))),
                    (string) ($row->data_source_note ?? ''),
                );
                yield $data;
            }
        })();

        $recordCount = $exports->create(
            $path,
            [
                'Examination' => (string) ($examination?->name ?? 'Selected Examination'),
                'Report' => $scope === 'qualified' ? 'Written qualified candidates' : 'All Written records',
                'Generated at' => now()->format('d-m-Y h:i A').' GMT+6',
                'Generated by' => (string) $request->user()->name,
                'Result finalized at' => $state->result_finalized_at?->format('d-m-Y h:i A').' GMT+6',
                'Order' => strtoupper($order).' '.strtoupper($direction),
            ],
            $headers,
            $rows,
        );

        $audit->record(
            'WRITTEN_ADMINISTRATIVE_XLSX_EXPORTED',
            $request->user(),
            'result_finalized',
            'result_finalized',
            'Created an administrative Excel export from the finalized Written result.',
            summary: [
                'scope' => $scope, 'order' => $order, 'direction' => $direction,
                'records' => $recordCount, 'filename' => $filename,
                'finalized_at' => $state->result_finalized_at?->toIso8601String(),
            ],
            batchId: $state->latest_import_batch_id,
        );

        $exportCache->prune($path);

        return response()->download(
            $path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function highMarks(Request $request): View
    {
        $this->authorize('viewAny', WrittenResult::class);
        $subject = trim((string) $request->query('subject', 'all'));
        $search = trim((string) $request->query('search', ''));
        $query = $this->highMarkQuery($subject, $search);

        return view('written.high-marks', [
            'rows' => $query->paginate(100)->withQueryString(),
            'filters' => compact('subject', 'search'),
            'subjects' => $this->reviewSubjectOptions(),
            'statistics' => $this->highMarkStatistics(),
            'uniqueCandidates' => DB::connection('exam')->table('written_candidate_marks as m')
                ->join('written_results as w', 'w.id', '=', 'm.written_result_id')
                ->where('w.status', 'active')->where('m.warning_codes', 'like', '%HIGH_MARK_REVIEW:%')
                ->distinct()->count('m.written_result_id'),
            'threshold' => (float) config('written.high_mark_review_percent', 75),
        ]);
    }

    public function paperCrashesXlsx(Request $request, AdministrativeXlsxExportService $exports, WrittenAuditService $audit): BinaryFileResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        return $this->reviewXlsx($request, $exports, $audit, 'paper_crash');
    }

    public function highMarksXlsx(Request $request, AdministrativeXlsxExportService $exports, WrittenAuditService $audit): BinaryFileResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        return $this->reviewXlsx($request, $exports, $audit, 'high_mark');
    }

    public function paperCrashesCsv(Request $request, WrittenAuditService $audit): StreamedResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        return $this->reviewCsv($request, $audit, 'paper_crash');
    }

    public function highMarksCsv(Request $request, WrittenAuditService $audit): StreamedResponse
    {
        $this->authorize('viewAny', WrittenResult::class);
        return $this->reviewCsv($request, $audit, 'high_mark');
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

    private function reviewSubjectOptions(): array
    {
        $codes = array_keys((array) config('written.subjects', []));
        $codes = array_values(array_filter($codes, static fn (string $code): bool => ! in_array($code, ['008', '009'], true)));
        $codes[] = '008_009';
        sort($codes, SORT_NATURAL);
        return $codes;
    }

    private function paperCrashQuery(string $subject = 'all', string $search = '')
    {
        $query = DB::connection('exam')->table('written_candidate_marks as m')
            ->join('written_results as w', 'w.id', '=', 'm.written_result_id')
            ->leftJoin('written_candidate_marks as m9', function ($join): void {
                $join->on('m9.written_result_id', '=', 'm.written_result_id')->where('m9.subject_code', '=', '009');
            })
            ->where('w.status', 'active')
            ->where('m.paper_crashed', 1)
            ->where('m.subject_code', '<>', '009')
            ->when($subject !== '' && $subject !== 'all', function ($q) use ($subject): void {
                $q->where('m.subject_code', $subject === '008_009' ? '008' : $subject);
            })
            ->when($search !== '', fn ($q) => $q->where(fn ($n) => $n->where('w.reg', $search)->orWhere('w.user_id', $search)))
            ->select([
                'm.id', 'm.subject_code', 'm.actual_mark', 'm.counted_mark', 'm.crash_threshold',
                'w.reg', 'w.user_id', 'w.cadre_category', 'w.written_qualified_track',
                'm9.actual_mark as mark009_actual', 'm9.counted_mark as mark009_counted',
            ])
            ->orderBy('w.reg', 'asc')->orderBy('m.subject_code');

        return $query;
    }

    private function highMarkQuery(string $subject = 'all', string $search = '')
    {
        $query = DB::connection('exam')->table('written_candidate_marks as m')
            ->join('written_results as w', 'w.id', '=', 'm.written_result_id')
            ->leftJoin('written_candidate_marks as m9', function ($join): void {
                $join->on('m9.written_result_id', '=', 'm.written_result_id')->where('m9.subject_code', '=', '009');
            })
            ->where('w.status', 'active')
            ->where('m.warning_codes', 'like', '%HIGH_MARK_REVIEW:%')
            ->where(function ($q): void {
                $q->whereNotIn('m.subject_code', ['008', '009'])
                    ->orWhere(function ($combined): void {
                        $combined->where('m.subject_code', '008')
                            ->where('m.warning_codes', 'like', '%HIGH_MARK_REVIEW:008_009:%');
                    });
            })
            ->when($subject !== '' && $subject !== 'all', function ($q) use ($subject): void {
                if ($subject === '008_009') {
                    $q->where('m.subject_code', '008')->where('m.warning_codes', 'like', '%HIGH_MARK_REVIEW:008_009:%');
                } else {
                    $q->where('m.subject_code', $subject)->where('m.warning_codes', 'like', '%HIGH_MARK_REVIEW:'.$subject.':%');
                }
            })
            ->when($search !== '', fn ($q) => $q->where(fn ($n) => $n->where('w.reg', $search)->orWhere('w.user_id', $search)))
            ->select([
                'm.id', 'm.subject_code', 'm.actual_mark', 'm.warning_codes',
                'w.reg', 'w.user_id', 'w.cadre_category', 'w.written_qualified_track',
                'm9.actual_mark as mark009_actual',
            ])
            ->orderBy('w.reg', 'asc')->orderBy('m.subject_code');

        return $query;
    }

    private function paperCrashStatistics(): array
    {
        $stats = [];
        foreach ($this->reviewSubjectOptions() as $code) {
            $subject = $code === '008_009' ? '008' : $code;
            $stats[$code] = DB::connection('exam')->table('written_candidate_marks as m')
                ->join('written_results as w', 'w.id', '=', 'm.written_result_id')
                ->where('w.status', 'active')->where('m.paper_crashed', 1)->where('m.subject_code', $subject)
                ->distinct()->count('m.written_result_id');
        }
        return $stats;
    }

    private function highMarkStatistics(): array
    {
        $stats = [];
        foreach ($this->reviewSubjectOptions() as $code) {
            $subject = $code === '008_009' ? '008' : $code;
            $needle = '%HIGH_MARK_REVIEW:'.$code.':%';
            $stats[$code] = DB::connection('exam')->table('written_candidate_marks as m')
                ->join('written_results as w', 'w.id', '=', 'm.written_result_id')
                ->where('w.status', 'active')->where('m.subject_code', $subject)->where('m.warning_codes', 'like', $needle)
                ->distinct()->count('m.written_result_id');
        }
        return $stats;
    }

    private function reviewXlsx(Request $request, AdministrativeXlsxExportService $exports, WrittenAuditService $audit, string $type): BinaryFileResponse
    {
        $subject = trim((string) $request->query('subject', 'all'));
        $search = trim((string) $request->query('search', ''));
        $query = $type === 'paper_crash' ? $this->paperCrashQuery($subject, $search) : $this->highMarkQuery($subject, $search);
        $directory = storage_path('app/private/written-review-exports');
        File::ensureDirectoryExists($directory);
        $label = $type === 'paper_crash' ? 'paper-crash' : 'high-mark-review';
        $filename = 'written-'.$label.'-'.now()->format('Ymd-His').'.xlsx';
        $path = $directory.DIRECTORY_SEPARATOR.uniqid($label.'-', true).'.xlsx';
        $threshold = $type === 'paper_crash'
            ? (float) config('written.paper_crash_percent', 30)
            : (float) config('written.high_mark_review_percent', 75);
        $rows = (function () use ($query, $type): \Generator {
            foreach ($query->cursor() as $row) {
                yield $this->reviewRow($row, $type);
            }
        })();
        $count = $exports->create(
            $path,
            [
                'Report' => $type === 'paper_crash' ? 'Written paper crash review' : 'Written high-mark review',
                'Generated at' => now()->format('d-m-Y h:i A').' GMT+6',
                'Generated by' => (string) $request->user()->name,
                'Subject filter' => $subject === 'all' ? 'All' : $this->reviewSubjectLabel($subject),
                'Search' => $search === '' ? 'None' : $search,
                'Configured threshold' => number_format($threshold, 2, '.', '').'%',
            ],
            ['Registration Number', 'User ID', 'Category', 'Qualified Track', 'Subject', 'Actual Mark', 'Full Mark', 'Percentage', $type === 'paper_crash' ? 'Crash Threshold' : 'Review Threshold'],
            $rows,
        );
        $audit->record(
            strtoupper('WRITTEN_'.($type === 'paper_crash' ? 'PAPER_CRASH' : 'HIGH_MARK').'_XLSX_EXPORTED'),
            $request->user(),
            reason: 'Created a Written review export.',
            summary: ['subject' => $subject, 'search' => $search, 'records' => $count, 'filename' => $filename],
        );
        return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    private function reviewCsv(Request $request, WrittenAuditService $audit, string $type): StreamedResponse
    {
        $subject = trim((string) $request->query('subject', 'all'));
        $search = trim((string) $request->query('search', ''));
        $query = $type === 'paper_crash' ? $this->paperCrashQuery($subject, $search) : $this->highMarkQuery($subject, $search);
        $filename = 'written-'.($type === 'paper_crash' ? 'paper-crash' : 'high-mark-review').'-'.now()->format('Ymd-His').'.csv';
        $audit->record(
            strtoupper('WRITTEN_'.($type === 'paper_crash' ? 'PAPER_CRASH' : 'HIGH_MARK').'_CSV_EXPORTED'),
            $request->user(),
            reason: 'Created a Written review CSV export.',
            summary: ['subject' => $subject, 'search' => $search, 'filename' => $filename],
        );
        return response()->streamDownload(function () use ($query, $type): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Registration Number', 'User ID', 'Category', 'Qualified Track', 'Subject', 'Actual Mark', 'Full Mark', 'Percentage', $type === 'paper_crash' ? 'Crash Threshold' : 'Review Threshold']);
            foreach ($query->cursor() as $row) {
                fputcsv($out, $this->reviewRow($row, $type));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reviewRow(object $row, string $type): array
    {
        $combined = (string) $row->subject_code === '008';
        $subject = $combined ? '008_009' : (string) $row->subject_code;
        $actual = $combined ? (float) $row->actual_mark + (float) ($row->mark009_actual ?? 0) : (float) $row->actual_mark;
        $full = $combined
            ? (float) data_get(config('written.subjects'), '008.full_mark', 50) + (float) data_get(config('written.subjects'), '009.full_mark', 50)
            : (float) data_get(config('written.subjects'), $subject.'.full_mark', 0);
        $percentage = $full > 0 ? ($actual / $full) * 100 : 0;
        $threshold = $type === 'paper_crash'
            ? ($combined ? (float) $row->crash_threshold : (float) $row->crash_threshold)
            : $full * ((float) config('written.high_mark_review_percent', 75) / 100);
        $category = match ((int) $row->cadre_category) { 1 => 'GG', 2 => 'TT', 3 => 'GT', default => (string) $row->cadre_category };
        $track = $row->written_qualified_track instanceof \BackedEnum ? $row->written_qualified_track->value : (string) ($row->written_qualified_track ?? '');

        return [
            (string) $row->reg,
            (string) $row->user_id,
            $category,
            $track,
            $this->reviewSubjectLabel($subject),
            number_format($actual, 2, '.', ''),
            number_format($full, 2, '.', ''),
            number_format($percentage, 2, '.', '').'%',
            number_format($threshold, 2, '.', ''),
        ];
    }

    private function reviewSubjectLabel(string $subject): string
    {
        return $subject === '008_009' ? '008 + 009 combined' : $subject;
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
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

    private function registrationLines($registrations, int $perLine, string $separator): string
    {
        if ($registrations->isEmpty()) {
            return 'No registration numbers.';
        }

        return $registrations
            ->chunk($perLine)
            ->map(fn ($row): string => $row->implode($separator))
            ->implode("\n");
    }

    private function registrationResultBlock($registrations, string $label, int $perLine, string $separator): string
    {
        $lines = $this->registrationLines($registrations, $perLine, $separator);
        $totalLabel = $label === 'ALL' ? 'TOTAL' : 'TOTAL ('.$label.')';

        return $lines."\n\n".$totalLabel.' = '.number_format($registrations->count());
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
