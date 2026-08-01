<?php

namespace App\Http\Controllers;

use App\Enums\PreliminaryProcessingStatus;
use App\Jobs\ApprovePreliminaryImport;
use App\Jobs\ValidatePreliminaryImport;
use App\Models\PreliminaryCutoffDecision;
use App\Models\PreliminaryDistributionReport;
use App\Models\PreliminaryImportBatch;
use App\Models\PreliminaryProcessingAudit;
use App\Models\PreliminaryProcessingState;
use App\Models\PreliminaryReconciliationReport;
use App\Models\PreliminaryResult;
use App\Services\Preliminary\PreliminaryAuditService;
use App\Services\Preliminary\PreliminaryCutoffService;
use App\Services\Preliminary\PreliminaryDistributionService;
use App\Services\Preliminary\PreliminaryImportService;
use App\Services\Preliminary\PreliminaryReconciliationService;
use App\Services\Preliminary\PreliminaryResultEditService;
use App\Services\Preliminary\PreliminaryTemplateService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
            'batches' => PreliminaryImportBatch::query()->latest('id')->paginate(15),
            'audits' => PreliminaryProcessingAudit::query()->latest('id')->limit(10)->get(),
            'counts' => [
                'results' => PreliminaryResult::query()->count(),
                'active' => PreliminaryResult::query()->where('candidate_status', 'active')->whereNotNull('mark')->count(),
                'cancelled' => PreliminaryResult::query()->where('candidate_status', 'cancelled')->count(),
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
        return view('preliminary.import-result', ['record' => $batch, 'rows' => $rows]);
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

    public function results(Request $request): View
    {
        $this->authorize('viewAny', PreliminaryResult::class);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $results = PreliminaryResult::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($x) => $x->where('reg', $search)->orWhere('user_id', $search)))
            ->when(in_array($status, ['active', 'cancelled'], true), fn ($q) => $q->where('candidate_status', $status))
            ->orderBy('reg')->paginate(100)->withQueryString();
        return view('preliminary.results', compact('results', 'search', 'status'));
    }

    public function edit(PreliminaryResult $result): View
    {
        $this->authorize('process', PreliminaryResult::class);
        $registration = DB::connection('exam')->table('registrations')->where('id', $result->registration_id)->first(['name', 'cadre_category']);
        $audits = PreliminaryProcessingAudit::query()->where('preliminary_result_id', $result->id)->latest('id')->limit(25)->get();
        return view('preliminary.edit', compact('result', 'registration', 'audits'));
    }

    public function update(Request $request, PreliminaryResult $result, PreliminaryResultEditService $service): RedirectResponse
    {
        $this->authorize('process', PreliminaryResult::class);
        $validated = $request->validate([
            'mark' => ['nullable', 'numeric', 'between:-9999.99,9999.99'],
            'candidate_status' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $service->update($result, $validated['mark'] ?? null, $validated['candidate_status'] ?? null, $validated['reason'], $request->user());
        return redirect()->route('preliminary.results.edit', $result)->with('success', 'Preliminary mark/status updated. Previous values were preserved in database audit and file log. Reconciliation/finalization snapshots were invalidated.');
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
