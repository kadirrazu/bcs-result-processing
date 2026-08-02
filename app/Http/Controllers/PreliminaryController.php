<?php

namespace App\Http\Controllers;

use App\Enums\PreliminaryProcessingStatus;
use App\Jobs\ApprovePreliminaryImport;
use App\Jobs\RevalidateAndMergeCorrectedImportRows;
use App\Jobs\FinalizePreliminaryResults;
use App\Jobs\ValidatePreliminaryImport;
use App\Models\ImportCorrectionEntry;
use App\Models\PreliminaryCutoffDecision;
use App\Models\PreliminaryDistributionReport;
use App\Models\PreliminaryImportBatch;
use App\Models\PreliminaryFinalizationRun;
use App\Models\PreliminaryProcessingAudit;
use App\Models\PreliminaryProcessingState;
use App\Models\PreliminaryReconciliationReport;
use App\Models\PreliminaryResult;
use App\Services\Documents\DocxPlaceholderTemplateService;
use App\Services\Preliminary\PreliminaryAuditService;
use App\Services\Preliminary\PreliminaryCutoffService;
use App\Services\Preliminary\PreliminaryDistributionService;
use App\Services\Preliminary\PreliminaryImportService;
use App\Services\Preliminary\PreliminaryReconciliationService;
use App\Services\Preliminary\PreliminaryResultEditService;
use App\Services\Preliminary\PreliminaryTemplateService;
use App\Services\Imports\InvalidRowCorrectionService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PreliminaryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', PreliminaryResult::class);

        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        return view('preliminary.index', [
            'state' => $state,
            'latestBatch' => PreliminaryImportBatch::query()->latest('id')->first(),
            'latestReconciliation' => PreliminaryReconciliationReport::query()->latest('id')->first(),
            'latestDistribution' => PreliminaryDistributionReport::query()->latest('id')->first(),
            'pendingCutoff' => PreliminaryCutoffDecision::query()->where('status', 'proposed')->latest('id')->first(),
            'currentCutoff' => PreliminaryCutoffDecision::query()->where('status', 'approved')->latest('id')->first(),
            'latestFinalization' => PreliminaryFinalizationRun::query()->latest('id')->first(),
            'batches' => PreliminaryImportBatch::query()->latest('id')->paginate(15),
            'audits' => PreliminaryProcessingAudit::query()->latest('id')->limit(10)->get(),
            'counts' => [
                'results' => PreliminaryResult::query()->count(),
                'active' => PreliminaryResult::query()->where('candidate_status', 'active')->whereNotNull('mark')->count(),
                'cancelled' => PreliminaryResult::query()->where('candidate_status', 'cancelled')->count(),
                'withheld' => PreliminaryResult::query()->where('candidate_status', 'withheld')->count(),
                'expelled' => PreliminaryResult::query()->where('candidate_status', 'expelled')->count(),
                'passed' => PreliminaryResult::query()->where('result_status', 'pass')->count(),
                'failed' => PreliminaryResult::query()->where('result_status', 'fail')->count(),
            ],
        ]);
    }

    public function store(Request $request, PreliminaryImportService $service, PreliminaryAuditService $audit, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:102400']]);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');
        $batch = $service->enqueue($validated['file'], $request->user()->id, $examinationId);
        $audit->record('MARK_IMPORT_QUEUED', $request->user(), null, 'queued', null, ['original_name' => $batch->original_name], batchId: $batch->id);

        return redirect()->route('preliminary.import.result', $batch)->with('success', 'Preliminary mark file queued for fast staging.');
    }

    public function template(PreliminaryTemplateService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $directory = storage_path('app/private/preliminary');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/preliminary-import-template.xlsx';
        $service->create($path);

        return response()->download($path, 'preliminary-import-template.xlsx')->deleteFileAfterSend();
    }

    public function result(PreliminaryImportBatch $batch)
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $rows = $batch->stagingRows()->whereIn('validation_status', ['invalid', 'warning', 'identity_conflict'])->orderBy('source_row')->paginate(100);
        return view('preliminary.import-result', [
            'record' => $batch,
            'rows' => $rows,
            'corrections' => ImportCorrectionEntry::query()->where('module', 'preliminary')->where('batch_id', $batch->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function correctionTemplate(PreliminaryImportBatch $batch, InvalidRowCorrectionService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $directory = storage_path('app/private/import-corrections');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/preliminary-batch-'.$batch->id.'-invalid-rows.xlsx';
        $count = $service->createCorrectionWorkbook('preliminary', (int) $batch->id, $path);
        abort_if($count === 0, 409, 'This batch has no invalid rows to correct.');

        return response()->download($path, 'preliminary-batch-'.$batch->id.'-invalid-rows.xlsx')->deleteFileAfterSend();
    }

    public function applyCorrections(Request $request, PreliminaryImportBatch $batch, InvalidRowCorrectionService $service, ExaminationContext $context, PreliminaryAuditService $audit): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        $validated = $request->validate([
            'correction_file' => ['required', 'file', 'mimes:xlsx,csv', 'max:102400'],
        ]);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $before = (string) $batch->status;
        $wasApproved = (int) $batch->approved_rows > 0 || $before === 'approved';
        $summary = $service->apply('preliminary', $batch, $validated['correction_file'], $request->user());
        $batch->update([
            'status' => 'validation_queued',
            'progress_percent' => 0,
            'failure_message' => null,
            'finished_at' => null,
        ]);
        if ($wasApproved) {
            RevalidateAndMergeCorrectedImportRows::dispatch(
                $examinationId, 'preliminary', (int) $batch->id, $summary['source_rows'], (int) $request->user()->id
            );
        } else {
            ValidatePreliminaryImport::dispatch($examinationId, $batch->id, (int) $request->user()->id);
        }
        $audit->record(
            'PRELIMINARY_INVALID_ROWS_CORRECTED',
            $request->user(),
            $before,
            'validation_queued',
            'Corrected invalid source rows before approval.',
            ['corrected_rows' => $summary['corrected_rows'], 'source_rows' => $summary['source_rows']],
            batchId: (int) $batch->id,
        );

        return back()->with('success', number_format($summary['corrected_rows']).' invalid Preliminary row(s) were replaced from the correction file. Validation is running again now.');
    }

    public function validateBatch(PreliminaryImportBatch $batch, ExaminationContext $context, Request $request, PreliminaryAuditService $audit): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        abort_unless(in_array($batch->status, ['staged', 'validated', 'failed'], true) && (int) $batch->approved_rows === 0, 409, 'Only unapproved staged/validated data can be validated.');
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');
        $before = $batch->status;
        $batch->update(['status' => 'validation_queued', 'progress_percent' => 0, 'failure_message' => null]);
        ValidatePreliminaryImport::dispatch($examinationId, $batch->id, $request->user()->id);
        $audit->record('MARK_VALIDATION_QUEUED', $request->user(), $before, 'validation_queued', null, batchId: $batch->id);
        return back()->with('success', 'Preliminary validation queued.');
    }

    public function approve(PreliminaryImportBatch $batch, ExaminationContext $context, Request $request, PreliminaryAuditService $audit): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        abort_unless($batch->status === 'validated' || ($batch->status === 'failed' && (int) $batch->approved_rows === 0 && ((int) $batch->valid_rows + (int) $batch->warning_rows) > 0), 409, 'Only validated data can be approved.');
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');
        $before = $batch->status;
        $batch->update(['status' => 'approval_queued', 'progress_percent' => 0, 'failure_message' => null]);
        ApprovePreliminaryImport::dispatch($examinationId, $batch->id, $request->user()->id);
        $audit->record('MARK_APPROVAL_QUEUED', $request->user(), $before, 'approval_queued', null, batchId: $batch->id);
        return back()->with('success', 'Eligible preliminary rows queued for approval and merge.');
    }

    public function status(PreliminaryImportBatch $batch): JsonResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $batch->refresh();
        return response()->json([
            'id' => $batch->id, 'status' => $batch->status, 'total_rows' => (int) $batch->total_rows,
            'processed_rows' => (int) $batch->processed_rows, 'staged_rows' => (int) $batch->staged_rows,
            'valid_rows' => (int) $batch->valid_rows, 'warning_rows' => (int) $batch->warning_rows,
            'invalid_rows' => (int) $batch->invalid_rows, 'identity_conflict_rows' => (int) $batch->identity_conflict_rows,
            'approved_rows' => (int) $batch->approved_rows, 'inserted_rows' => (int) $batch->inserted_rows,
            'updated_rows' => (int) $batch->updated_rows, 'progress_percent' => (float) $batch->progress_percent,
            'failure_message' => $batch->failure_message,
            'finished' => ! in_array($batch->status, ['queued', 'staging', 'validation_queued', 'validating', 'approval_queued', 'approving'], true),
        ]);
    }

    public function report(PreliminaryImportBatch $batch): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        set_time_limit(0);
        $directory = storage_path('app/private/preliminary-import-reports');
        File::ensureDirectoryExists($directory);
        $filename = "preliminary-import-batch-{$batch->id}-issues.csv";
        $path = $directory.DIRECTORY_SEPARATOR.uniqid("batch-{$batch->id}-", true).'.csv';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("\xEF\xBB\xBF");
        $file->fputcsv(['source_row', 'reg', 'user', 'raw_mark', 'candidate_status', 'validation_status', 'warnings', 'errors']);
        DB::connection('exam')->table('preliminary_import_staging')
            ->select(['id', 'source_row', 'reg', 'user_id', 'raw_mark', 'raw_candidate_status', 'validation_status', 'validation_warnings', 'validation_errors'])
            ->where('batch_id', $batch->id)->whereIn('validation_status', ['invalid', 'warning', 'identity_conflict'])
            ->chunkById(5000, function ($rows) use ($file): void {
                foreach ($rows as $row) {
                    $file->fputcsv([$row->source_row, $row->reg, $row->user_id, $row->raw_mark, $row->raw_candidate_status, $row->validation_status, $this->csvMessages($row->validation_warnings), $this->csvMessages($row->validation_errors)]);
                }
            }, 'id');
        unset($file);
        return response()->download($path, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, no-cache, must-revalidate'])->deleteFileAfterSend(true);
    }

    public function generateReconciliation(Request $request, PreliminaryReconciliationService $service, PreliminaryAuditService $audit): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        $state = PreliminaryProcessingState::query()->firstOrCreate(['id' => 1], ['status' => PreliminaryProcessingStatus::NotStarted->value]);
        abort_if($state->latest_import_batch_id === null, 409, 'Approve a preliminary mark import before generating reconciliation.');
        $beforeStatus = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
        $generated = $service->generate((int) $request->user()->id);
        $audit->record(
            'PRESENT_ABSENT_REPORT_GENERATED', $request->user(), $beforeStatus, PreliminaryProcessingStatus::ReconciliationGenerated->value,
            null, $generated['summary'], null, $generated['summary'], batchId: $state->latest_import_batch_id, processingRunId: $generated['report']->id,
        );
        return redirect()->route('preliminary.reconciliation.show', $generated['report'])->with('success', 'Present / absent reconciliation generated.');
    }

    public function reconciliation(PreliminaryReconciliationReport $report): View
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        return view('preliminary.reconciliation', ['report' => $report, 'summary' => $report->summary]);
    }

    public function reconciliationCsv(PreliminaryReconciliationReport $report, string $group): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        abort_unless(in_array($group, ['present_status', 'cancelled_reason', 'cancelled_no_reason', 'absent'], true), 404);
        set_time_limit(0);
        $directory = storage_path('app/private/preliminary-reconciliation-reports');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.uniqid("reconciliation-{$report->id}-{$group}-", true).'.csv';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("\xEF\xBB\xBF");
        $file->fputcsv(['reg', 'user', 'name', 'cadre_category', 'mark', 'candidate_status']);

        $query = DB::connection('exam')->table('registrations as r')
            ->leftJoin('preliminary_results as p', 'p.registration_id', '=', 'r.id')
            ->where('r.status', 'active')
            ->select(['r.id', 'r.reg', 'r.user_id', 'r.name', 'r.cadre_category', 'p.mark', 'p.raw_candidate_status']);

        match ($group) {
            'present_status' => $query->where('p.candidate_status', 'active')->whereNotNull('p.mark')->whereNotNull('p.raw_candidate_status')->whereRaw("TRIM(p.raw_candidate_status) <> ''"),
            'cancelled_reason' => $query->where('p.candidate_status', 'cancelled')->whereNotNull('p.raw_candidate_status')->whereRaw("TRIM(p.raw_candidate_status) <> ''"),
            'cancelled_no_reason' => $query->where('p.candidate_status', 'cancelled')->where(fn ($q) => $q->whereNull('p.raw_candidate_status')->orWhereRaw("TRIM(p.raw_candidate_status) = ''")),
            'absent' => $query->whereNull('p.id'),
        };

        $query->orderBy('r.id')->chunkById(5000, function ($rows) use ($file): void {
            foreach ($rows as $row) {
                $file->fputcsv([$row->reg, $row->user_id, $row->name, $this->categoryLabel((int) $row->cadre_category), $row->mark, $row->raw_candidate_status]);
            }
        }, 'r.id', 'id');
        unset($file);

        return response()->download($path, "preliminary-reconciliation-{$report->id}-{$group}.csv", ['Content-Type' => 'text/csv; charset=UTF-8'])->deleteFileAfterSend(true);
    }


    public function generateDistribution(Request $request, PreliminaryDistributionService $service, PreliminaryAuditService $audit): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        $state = PreliminaryProcessingState::query()->firstOrCreate(['id' => 1], ['status' => PreliminaryProcessingStatus::NotStarted->value]);
        $beforeStatus = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
        $generated = $service->generate((int) $request->user()->id);

        $audit->record(
            'MARK_DISTRIBUTION_GENERATED',
            $request->user(),
            $beforeStatus,
            $state->cutoff_mark !== null ? PreliminaryProcessingStatus::CutoffSet->value : PreliminaryProcessingStatus::DistributionGenerated->value,
            null,
            $generated['summary'],
            null,
            $generated['summary'],
            batchId: $state->latest_import_batch_id,
            processingRunId: $generated['report']->id,
        );

        return redirect()->route('preliminary.distribution.show', $generated['report'])
            ->with('success', 'Mark distribution and cumulative report generated.');
    }

    public function distribution(PreliminaryDistributionReport $report): View
    {
        $this->authorize('viewAny', PreliminaryResult::class);

        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        return view('preliminary.distribution', [
            'report' => $report,
            'rows' => $report->distribution ?? [],
            'state' => $state,
            'pendingCutoff' => PreliminaryCutoffDecision::query()->where('status', 'proposed')->latest('id')->first(),
            'currentCutoff' => PreliminaryCutoffDecision::query()->where('status', 'approved')->latest('id')->first(),
            'latestFinalization' => PreliminaryFinalizationRun::query()->latest('id')->first(),
            'cutoffHistory' => PreliminaryCutoffDecision::query()->latest('id')->limit(25)->get(),
        ]);
    }

    public function distributionCsv(PreliminaryDistributionReport $report): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);

        $directory = storage_path('app/private/preliminary-distribution-reports');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.uniqid("distribution-{$report->id}-", true).'.csv';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("\xEF\xBB\xBF");
        $file->fputcsv(['mark', 'count_total', 'count_GG', 'count_TT', 'count_GT', 'cumulative_total', 'cumulative_GG', 'cumulative_TT', 'cumulative_GT']);

        foreach (($report->distribution ?? []) as $row) {
            $file->fputcsv([
                $row['mark'],
                $row['count']['total'], $row['count']['GG'], $row['count']['TT'], $row['count']['GT'],
                $row['cumulative']['total'], $row['cumulative']['GG'], $row['cumulative']['TT'], $row['cumulative']['GT'],
            ]);
        }
        unset($file);

        return response()->download(
            $path,
            "preliminary-mark-distribution-{$report->id}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        )->deleteFileAfterSend(true);
    }

    public function proposeCutoff(Request $request, PreliminaryCutoffService $service): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        $validated = $request->validate([
            'cutoff_mark' => ['required', 'numeric', 'between:-9999.99,9999.99'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $decision = $service->propose((float) $validated['cutoff_mark'], $validated['reason'], $request->user());

        return redirect()->route('preliminary.distribution.show', $decision->distribution_report_id)
            ->with('success', 'Cut-off proposal saved with audit trail. Review the projected pass counts and approve when ready.');
    }

    public function approveCutoff(Request $request, PreliminaryCutoffDecision $decision, PreliminaryCutoffService $service): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        $validated = $request->validate([
            'approval_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $service->approve($decision, $validated['approval_reason'], $request->user());

        return redirect()->route('preliminary.distribution.show', $decision->distribution_report_id)
            ->with('success', 'Cut-off approved and recorded in database audit and preliminary file log.');
    }

    public function finalizeResults(Request $request, ExaminationContext $context, PreliminaryAuditService $audit): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        abort_if($state->current_cutoff_decision_id === null || $state->cutoff_mark === null, 409, 'Approve a cut-off before finalizing results.');
        abort_if((bool) $state->cutoff_requires_review, 409, 'The approved cut-off requires review before finalization.');
        abort_if($state->latest_distribution_report_id === null, 409, 'Generate the current mark distribution before finalization.');

        $decision = PreliminaryCutoffDecision::query()->findOrFail($state->current_cutoff_decision_id);
        abort_unless($decision->status === 'approved', 409, 'Current cut-off is not approved.');

        $running = PreliminaryFinalizationRun::query()->whereIn('status', ['queued', 'running'])->exists();
        abort_if($running, 409, 'A preliminary finalization is already queued/running.');

        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $beforeStatus = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
        $run = PreliminaryFinalizationRun::query()->create([
            'cutoff_decision_id' => $decision->id,
            'cutoff_mark' => $decision->cutoff_mark,
            'status' => 'queued',
            'reason' => $validated['reason'],
            'queued_by' => $request->user()->id,
            'queued_at' => now(),
            'current_step' => 'Waiting for queue worker',
            'total_rows' => 0,
            'processed_rows' => 0,
            'progress_percent' => 0,
        ]);

        $state->update([
            'status' => PreliminaryProcessingStatus::ResultFinalizing->value,
            'latest_finalization_run_id' => $run->id,
        ]);

        FinalizePreliminaryResults::dispatch($examinationId, $run->id, (int) $request->user()->id);

        $audit->record(
            'RESULT_FINALIZATION_QUEUED',
            $request->user(),
            $beforeStatus,
            PreliminaryProcessingStatus::ResultFinalizing->value,
            $validated['reason'],
            [
                'run_id' => $run->id,
                'cutoff_decision_id' => $decision->id,
                'cutoff_mark' => $decision->cutoff_mark,
            ],
            batchId: $state->latest_import_batch_id,
            processingRunId: $run->id,
        );

        return back()->with('success', 'Preliminary result finalization queued.');
    }

    public function finalizationStatus(PreliminaryFinalizationRun $run): JsonResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $run->refresh();

        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'cutoff_mark' => $run->cutoff_mark,
            'current_step' => $run->current_step,
            'total_rows' => (int) $run->total_rows,
            'processed_rows' => (int) $run->processed_rows,
            'progress_percent' => (float) $run->progress_percent,
            'summary' => $run->summary,
            'failure_message' => $run->failure_message,
            'queued_at' => $run->queued_at?->format('d-m-Y h:i:s A'),
            'started_at' => $run->started_at?->format('d-m-Y h:i:s A'),
            'completed_at' => $run->completed_at?->format('d-m-Y h:i:s A'),
            'timezone' => config('app.timezone'),
            'finished' => in_array($run->status, ['completed', 'failed'], true),
        ]);
    }

    public function finalResultCombined(): View
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $state = $this->finalizedState();

        return view('preliminary.final-result-combined', [
            'state' => $state,
            'registrations' => $this->passedRegistrations(),
        ]);
    }

    public function finalResultCategory(): View
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $state = $this->finalizedState();

        return view('preliminary.final-result-category', [
            'state' => $state,
            'groups' => [
                'GG' => $this->passedRegistrations(1),
                'TT' => $this->passedRegistrations(2),
                'GT' => $this->passedRegistrations(3),
            ],
        ]);
    }

    public function finalResultCombinedTxt(): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $this->finalizedState();
        $regs = $this->passedRegistrations();

        $directory = storage_path('app/private/preliminary-final-results');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.uniqid('preliminary-result-combined-', true).'.txt';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("PRELIMINARY EXAMINATION RESULT - ALL CATEGORIES COMBINED\r\n\r\n");
        foreach ($regs->chunk(10) as $row) {
            $file->fwrite($row->implode('    ')."\r\n");
        }
        $file->fwrite("\r\nTOTAL = ".number_format($regs->count())."\r\n");
        unset($file);

        return response()->download($path, 'preliminary-result-combined.txt', ['Content-Type' => 'text/plain; charset=UTF-8'])->deleteFileAfterSend(true);
    }

    public function finalResultCategoryTxt(): BinaryFileResponse
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $this->finalizedState();
        $groups = ['GG' => $this->passedRegistrations(1), 'TT' => $this->passedRegistrations(2), 'GT' => $this->passedRegistrations(3)];

        $directory = storage_path('app/private/preliminary-final-results');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.uniqid('preliminary-result-category-', true).'.txt';
        $file = new \SplFileObject($path, 'wb');
        $file->fwrite("PRELIMINARY EXAMINATION RESULT - CATEGORY WISE\r\n");

        foreach ($groups as $category => $regs) {
            $file->fwrite("\r\n{$category}\r\n");
            $file->fwrite(str_repeat('-', 40)."\r\n");
            foreach ($regs->chunk(10) as $row) {
                $file->fwrite($row->implode('    ')."\r\n");
            }
            $file->fwrite("\r\nTOTAL ({$category}) = ".number_format($regs->count())."\r\n");
        }
        unset($file);

        return response()->download($path, 'preliminary-result-category-wise.txt', ['Content-Type' => 'text/plain; charset=UTF-8'])->deleteFileAfterSend(true);
    }

    public function finalResultTemplate(ExaminationContext $context): View
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $state = $this->finalizedState();

        return view('preliminary.fill-result-template', [
            'state' => $state,
            'examination' => $context->current(),
            'defaultPerLine' => max(1, min(12, (int) config('result-documents.registrations_per_line', 8))),
        ]);
    }

    public function generateFinalResultTemplate(
        Request $request,
        ExaminationContext $context,
        DocxPlaceholderTemplateService $documents,
        PreliminaryAuditService $audit,
    ): BinaryFileResponse {
        $this->authorize('viewAny', PreliminaryResult::class);
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

        $all = $this->passedRegistrations();
        $groups = [
            'GG' => $this->passedRegistrations(1),
            'TT' => $this->passedRegistrations(2),
            'GT' => $this->passedRegistrations(3),
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
            'CUTOFF_MARK' => number_format((float) $state->cutoff_mark, 2, '.', ''),
            'FINALIZED_DATE' => $state->result_finalized_at?->format('d-m-Y') ?? '',
        ];

        $directory = storage_path('app/private/preliminary-publishing-documents');
        File::ensureDirectoryExists($directory);
        $examSlug = Str::slug((string) ($examination?->name ?? 'preliminary-examination')) ?: 'preliminary-examination';
        $outputName = $examSlug.'-preliminary-result-'.now()->format('Ymd-His').'.docx';
        $outputPath = $directory.DIRECTORY_SEPARATOR.uniqid('publishing-', true).'.docx';

        try {
            $summary = $documents->fill($validated['template_file']->getRealPath(), $outputPath, $replacements);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'The Word template could not be filled. Please check that it is a valid .docx file and try again.');
        }

        $audit->record(
            'PRELIMINARY_PUBLISHING_DOCUMENT_CREATED',
            $request->user(),
            'finalized',
            'finalized',
            'Created a Word publishing document from the finalized Preliminary result.',
            summary: [
                'template_name' => $validated['template_file']->getClientOriginalName(),
                'output_name' => $outputName,
                'registrations_per_line' => $perLine,
                'placeholder_replacements' => $summary['replaced'],
                'total_replacements' => $summary['total_replacements'],
                'unrecognized_placeholders' => $summary['unknown_placeholders'],
                'cutoff_mark' => (float) $state->cutoff_mark,
                'finalized_at' => $state->result_finalized_at?->toIso8601String(),
            ],
            batchId: $state->latest_import_batch_id,
            processingRunId: $state->latest_finalization_run_id,
        );

        return response()->download(
            $outputPath,
            $outputName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
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

    private function finalizedState(): PreliminaryProcessingState
    {
        $state = PreliminaryProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => PreliminaryProcessingStatus::NotStarted->value],
        );

        abort_if($state->result_finalized_at === null, 409, 'Preliminary result has not been finalized yet.');
        abort_if((bool) $state->cutoff_requires_review, 409, 'Preliminary result is stale because the cut-off requires review.');

        return $state;
    }

    private function passedRegistrations(?int $category = null)
    {
        return DB::connection('exam')->table('preliminary_results as p')
            ->join('registrations as r', 'r.id', '=', 'p.registration_id')
            ->where('r.status', 'active')
            ->where('p.result_status', 'pass')
            ->when($category !== null, fn ($query) => $query->where('r.cadre_category', $category))
            // Registration number order is authoritative for the published list; mark order is never used here.
            ->orderByRaw('CAST(p.reg AS UNSIGNED) ASC')
            ->pluck('p.reg');
    }

    public function results(Request $request): View
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $results = PreliminaryResult::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x->where('reg', $search)->orWhere('user_id', $search)))
            ->when(in_array($status, ['active', 'cancelled', 'withheld', 'expelled'], true), fn ($q) => $q->where('candidate_status', $status))
            ->orderBy('reg')->paginate(100)->withQueryString();
        return view('preliminary.results', compact('results', 'search', 'status'));
    }

    public function edit(PreliminaryResult $result): View
    {
        $this->authorize('process', PreliminaryResult::class);
        $registration = DB::connection('exam')->table('registrations')->where('id', $result->registration_id)->first(['name', 'cadre_category']);
        $audits = PreliminaryProcessingAudit::query()->where('preliminary_result_id', $result->id)->latest('id')->limit(25)->get();
        return view('preliminary.edit', [
            'result' => $result,
            'registration' => $registration,
            'audits' => $audits,
            'statusOptions' => array_map(
                static fn (\App\Enums\PreliminaryCandidateStatus $status): string => $status->value,
                \App\Enums\PreliminaryCandidateStatus::cases(),
            ),
        ]);
    }

    public function update(Request $request, PreliminaryResult $result, PreliminaryResultEditService $service): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        $validated = $request->validate([
            'mark' => ['nullable', 'numeric', 'between:-9999.99,9999.99'],
            'source_note' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', 'in:active,cancelled,withheld,expelled'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $service->update(
            $result,
            $validated['mark'] ?? null,
            $validated['source_note'] ?? null,
            $validated['status'],
            $validated['reason'],
            $request->user(),
        );
        return redirect()->route('preliminary.results.edit', $result)
            ->with('success', 'Preliminary record updated and the previous values were kept in the audit history. Result-processing steps now need to be generated again.');
    }

    private function csvMessages(mixed $value): string
    {
        if ($value === null || $value === '') { return ''; }
        if (is_array($value)) { return implode(' | ', array_map('strval', $value)); }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? implode(' | ', array_map('strval', $decoded)) : (string) $value;
    }

    private function categoryLabel(int $value): string
    {
        return match ($value) { 1 => 'GG', 2 => 'TT', 3 => 'GT', default => (string) $value };
    }
}
