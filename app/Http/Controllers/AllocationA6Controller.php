<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAllocationA6Export;
use App\Models\AllocationA4Result;
use App\Models\AllocationA5Run;
use App\Models\AllocationA6ExportAudit;
use App\Models\Registration;
use App\Models\ReportingExportRun;
use App\Models\User;
use App\Services\Allocation\AllocationA6DocxSampleTemplateService;
use App\Services\Allocation\AllocationA6ExcelFieldCatalog;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Services\Allocation\AllocationA6ReportService;
use App\Services\Allocation\AllocationA6SummaryService;
use App\Services\Allocation\AllocationResultDispositionService;
use App\Services\Reporting\ReportExportFileStore;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AllocationA6Controller extends Controller
{
    public function __construct(private readonly AllocationResultDispositionService $dispositions) {}
    public function index(AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $gate = $readiness->inspect();
        $cadres = $gate['ready'] ? $reports->cadres($gate['a5']) : collect();
        $dispositionSnapshot = $gate['ready'] ? $this->dispositions->snapshot($gate['a5']) : null;
        $audits = AllocationA6ExportAudit::query()->latest('id')->limit(10)->get();
        $exportRuns = ReportingExportRun::query()->where('module', 'allocation_a6')->latest('id')->limit(10)->get();

        return view('allocation.a6.index', compact('gate', 'cadres', 'audits', 'exportRuns', 'dispositionSnapshot'));
    }

    public function candidates(Request $request, AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $a5 = $readiness->requireReady();
        $search = trim((string) $request->query('search', ''));
        $baseQuery = $reports->tabulationEligibleQuery();
        $totalCandidates = (clone $baseQuery)->count();
        $query = $baseQuery
            ->join('registrations as r', 'r.id', '=', 'tabulation_results.registration_id')
            ->select('tabulation_results.*', 'r.name as candidate_name', 'r.user_id as registration_user_id');
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('r.reg', 'like', '%'.$search.'%')
                    ->orWhere('r.user_id', 'like', '%'.$search.'%')
                    ->orWhere('r.name', 'like', '%'.$search.'%');
            });
        }
        $results = $query->orderBy('r.reg')->paginate(100)->withQueryString();
        $allocatedRegs = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->whereIn('reg', $results->pluck('reg'))->get()->keyBy('reg');
        $allocationAbbr = $reports->abbreviations($allocatedRegs->pluck('cadre_code'));

        return view('allocation.a6.candidates', compact('a5', 'results', 'search', 'allocatedRegs', 'allocationAbbr', 'totalCandidates'));
    }

    public function candidate(string $reg, AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $a5 = $readiness->requireReady();
        $data = $reports->candidateDetail($reg, $a5);

        return view('allocation.a6.candidate-show', compact('a5', 'data'));
    }

    public function cadres(AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $a5 = $readiness->requireReady();
        $cadres = $reports->cadres($a5);

        return view('allocation.a6.cadres', compact('a5', 'cadres'));
    }

    public function cadre(Request $request, int $cadreCode, AllocationA6ReadinessService $readiness, AllocationA6ReportService $reports): View
    {
        $a5 = $readiness->requireReady();
        $cadre = $reports->cadres($a5)->firstWhere('code', $cadreCode);
        abort_if($cadre === null, 404);
        $status = strtoupper(trim((string) $request->query('status', 'ACTIVE')));
        if (! in_array($status, ['ACTIVE','WITHHELD','CANCELLED','ALL_INTERNAL'], true)) $status = 'ACTIVE';

        $query = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->where('cadre_code', $cadreCode);
        if ($status === 'ACTIVE') {
            $this->dispositions->applyPublishedOnly($query, $a5, 'allocation_a4_results.registration_id');
        } elseif (in_array($status, ['WITHHELD','CANCELLED'], true)) {
            $query->whereExists(function ($sub) use ($a5, $status): void {
                $sub->selectRaw('1')->from('allocation_result_dispositions as ard')
                    ->whereColumn('ard.registration_id', 'allocation_a4_results.registration_id')
                    ->where('ard.allocation_a5_run_id', $a5->id)->where('ard.status', $status);
            });
        }
        $results = $query->orderBy('merit_position')->orderBy('reg')->paginate(100)->withQueryString();
        $names = Registration::query()->whereIn('id', $results->pluck('registration_id'))->pluck('name', 'id');
        $dispositionMap = $this->dispositions->dispositionMap($a5, $results->pluck('registration_id'));

        return view('allocation.a6.cadre-show', compact('a5', 'cadre', 'results', 'names', 'status', 'dispositionMap'));
    }

    public function summary(
        AllocationA6ReadinessService $readiness,
        AllocationA6SummaryService $summary,
    ): View {
        $a5 = $readiness->requireReady();
        $rows = $summary->rows($a5);
        $totals = $summary->totals($rows);

        return view('allocation.a6.summary', compact('a5', 'rows', 'totals'));
    }

    public function shortSummary(
        AllocationA6ReadinessService $readiness,
        AllocationA6SummaryService $summary,
    ): View {
        $a5 = $readiness->requireReady();
        $rows = $summary->rows($a5);
        $totals = $summary->totals($rows);

        return view('allocation.a6.summary-short', compact('a5', 'rows', 'totals'));
    }

    public function startShortSummaryExport(
        Request $request,
        AllocationA6ReadinessService $readiness,
        ExaminationContext $context,
    ): RedirectResponse {
        $a5 = $readiness->requireReadyStrict();
        $validated = $request->validate([
            'format' => ['required', 'in:xlsx,pdf'],
        ]);

        $format = strtoupper((string) $validated['format']);
        $run = $this->queueRun(
            $a5,
            $format,
            'allocation_summary_short',
            ['report' => 'allocation_summary_short'],
            $request,
            $context,
        );

        return redirect()->route('allocation.a6.exports.show', $run)
            ->with('success', 'Short Allocation Summary '.$format.' export queued. Progress will update automatically.');
    }

    public function startSummaryExport(
        Request $request,
        AllocationA6ReadinessService $readiness,
        ExaminationContext $context,
    ): RedirectResponse {
        $a5 = $readiness->requireReadyStrict();
        $validated = $request->validate([
            'format' => ['required', 'in:xlsx,pdf'],
        ]);

        $format = strtoupper((string) $validated['format']);
        $run = $this->queueRun(
            $a5,
            $format,
            'allocation_summary',
            ['report' => 'allocation_summary'],
            $request,
            $context,
        );

        return redirect()->route('allocation.a6.exports.show', $run)
            ->with('success', 'Allocation Summary '.$format.' export queued. Progress will update automatically.');
    }

    public function startTxt(
        Request $request,
        AllocationA6ReadinessService $readiness,
        ExaminationContext $context,
    ): RedirectResponse {
        $a5 = $readiness->requireReadyStrict();
        $validated = $request->validate([
            'mode' => ['required', 'in:consolidated,cadre_zip'],
            'registrations_per_line' => ['required', 'integer', 'min:1', 'max:20'],
            'report_title' => ['nullable', 'string', 'max:150'],
        ]);
        $title = trim((string) ($validated['report_title'] ?? 'Final Cadre Allocation')) ?: 'Final Cadre Allocation';
        $parameters = [
            'mode' => (string) $validated['mode'],
            'registrations_per_line' => (int) $validated['registrations_per_line'],
            'report_title' => $title,
        ];
        $run = $this->queueRun($a5, 'TXT', (string) $validated['mode'], $parameters, $request, $context);

        return redirect()->route('allocation.a6.exports.show', $run)->with('success', 'TXT export queued. Progress will update automatically.');
    }

    public function startXlsx(
        Request $request,
        AllocationA6ReadinessService $readiness,
        ExaminationContext $context,
    ): RedirectResponse {
        $a5 = $readiness->requireReadyStrict();
        $validated = $request->validate([
            'scope' => ['required', 'in:tabulation_eligible,allocated,cadre'],
            'cadre_code' => ['nullable', 'integer'],
        ]);
        $cadre = $validated['scope'] === 'cadre' ? (int) ($validated['cadre_code'] ?? 0) : null;
        if ($validated['scope'] === 'cadre' && $cadre < 1) {
            abort(422, 'Cadre code is required.');
        }
        $run = $this->queueRun(
            $a5,
            'XLSX',
            (string) $validated['scope'],
            ['cadre_code' => $cadre, 'preset' => 'standard_final_report'],
            $request,
            $context,
        );

        return redirect()->route('allocation.a6.exports.show', $run)->with('success', 'Excel export queued. Progress will update automatically.');
    }

    public function excelBuilder(
        AllocationA6ReadinessService $readiness,
        AllocationA6ReportService $reports,
        AllocationA6ExcelFieldCatalog $catalog,
    ): View {
        $a5 = $readiness->requireReady();
        $groups = $catalog->groups();
        $cadres = $reports->cadres($a5);

        return view('allocation.a6.excel-builder', compact('a5', 'groups', 'cadres'));
    }

    public function startDynamicXlsx(
        Request $request,
        AllocationA6ReadinessService $readiness,
        AllocationA6ExcelFieldCatalog $catalog,
        ExaminationContext $context,
    ): RedirectResponse {
        $a5 = $readiness->requireReadyStrict();
        $validated = $request->validate([
            'scope' => ['required', 'in:tabulation_eligible,allocated,cadre'],
            'cadre_code' => ['nullable', 'integer'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'max:100'],
        ]);
        $fields = $catalog->validateSelection((array) $validated['fields']);
        $cadre = $validated['scope'] === 'cadre' ? (int) ($validated['cadre_code'] ?? 0) : null;
        if ($validated['scope'] === 'cadre' && $cadre < 1) {
            abort(422, 'Cadre code is required.');
        }
        $labels = $catalog->fields();
        $run = $this->queueRun(
            $a5,
            'XLSX',
            (string) $validated['scope'],
            [
                'cadre_code' => $cadre,
                'preset' => 'dynamic_field_selection',
                'selected_fields' => $fields,
                'selected_field_labels' => collect($fields)->mapWithKeys(fn ($key) => [$key => $labels[$key]])->all(),
            ],
            $request,
            $context,
        );

        return redirect()->route('allocation.a6.exports.show', $run)->with('success', 'Custom Excel export queued. Progress will update automatically.');
    }

    public function docx(AllocationA6ReadinessService $readiness, ExaminationContext $context): View
    {
        $a5 = $readiness->requireReady();

        return view('allocation.a6.docx', ['a5' => $a5, 'examination' => $context->current(), 'defaultPerLine' => 8]);
    }

    public function downloadDocxSample(
        AllocationA6ReadinessService $readiness,
        AllocationA6DocxSampleTemplateService $samples,
        ExaminationContext $context,
    ): BinaryFileResponse {
        $a5 = $readiness->requireReady();
        [$path, $name] = $samples->build($a5, (string) ($context->current()?->name ?? ''));

        return response()->download(
            $path,
            $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }

    public function generateDocx(
        Request $request,
        AllocationA6ReadinessService $readiness,
        ExaminationContext $context,
        ReportExportFileStore $files,
    ): RedirectResponse {
        $a5 = $readiness->requireReadyStrict();
        $validated = $request->validate([
            'template_file' => ['required', 'file', 'mimes:docx', 'max:20480'],
            'result_date' => ['required', 'date'],
            'registrations_per_line' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $run = $this->createRun(
            $a5,
            'DOCX',
            'template',
            [
                'template_name' => $request->file('template_file')->getClientOriginalName(),
                'result_date' => date('d-m-Y', strtotime((string) $validated['result_date'])),
                'registrations_per_line' => (int) $validated['registrations_per_line'],
            ],
            $request->user()?->id,
        );

        try {
            $parameters = (array) $run->parameters;
            $parameters['template_path'] = $files->storeUploadedSource('allocation-a6', $run->id, $request->file('template_file'));
            $run->forceFill(['parameters' => $parameters])->save();
            $this->dispatchRun($run, $a5, $context);
        } catch (\Throwable $e) {
            $run->delete();
            throw $e;
        }

        return redirect()->route('allocation.a6.exports.show', $run)->with('success', 'DOCX generation queued. Progress will update automatically.');
    }

    public function exportRun(ReportingExportRun $exportRun): View
    {
        $this->assertA6Run($exportRun);

        $generatedByUser = $exportRun->generated_by ? User::query()->find((int) $exportRun->generated_by) : null;
        $outdated = $exportRun->status === 'completed' && ! $this->isDispositionSnapshotCurrent($exportRun);

        return view('allocation.a6.export-show', ['run' => $exportRun, 'generatedByUser' => $generatedByUser, 'outdated' => $outdated]);
    }

    public function exportStatus(ReportingExportRun $exportRun): JsonResponse
    {
        $this->assertA6Run($exportRun);
        $exportRun->refresh();

        $outdated = $exportRun->status === 'completed' && ! $this->isDispositionSnapshotCurrent($exportRun);
        return response()->json([
            'status' => $outdated ? 'outdated' : $exportRun->status,
            'phase' => $exportRun->phase,
            'progress_percent' => (int) $exportRun->progress_percent,
            'progress_current' => (int) $exportRun->progress_current,
            'progress_total' => (int) $exportRun->progress_total,
            'progress_message' => $exportRun->progress_message,
            'failure_message' => $exportRun->failure_message,
            'finished' => $exportRun->isFinished(),
            'download_url' => $exportRun->status === 'completed' && ! $outdated ? route('allocation.a6.exports.download', $exportRun) : null,
        ]);
    }

    public function download(ReportingExportRun $exportRun): BinaryFileResponse
    {
        $this->assertA6Run($exportRun);
        abort_unless($exportRun->status === 'completed', 409, 'Export is not ready for download.');
        $snapshot = (array) $exportRun->source_snapshot;
        $a5 = AllocationA5Run::query()->find((int) ($snapshot['allocation_a5_run_id'] ?? 0));
        abort_unless($a5, 409, 'Export source A5 is unavailable.');
        $currentDisposition = $this->dispositions->snapshot($a5);
        abort_if(
            (string) ($snapshot['disposition_hash'] ?? '') === '' || ! hash_equals((string) $snapshot['disposition_hash'], (string) $currentDisposition['hash']),
            409,
            'This A6 export is OUTDATED because A5.5 publication status changed. Regenerate the report before download.'
        );
        abort_unless($exportRun->file_path && File::isFile($exportRun->file_path), 404, 'Generated export file is missing.');

        return response()->download(
            $exportRun->file_path,
            (string) $exportRun->file_name,
            ['Content-Type' => (string) ($exportRun->file_mime ?: 'application/octet-stream')]
        );
    }

    private function queueRun(
        AllocationA5Run $a5,
        string $type,
        string $scope,
        array $parameters,
        Request $request,
        ExaminationContext $context,
    ): ReportingExportRun {
        $run = $this->createRun($a5, $type, $scope, $parameters, $request->user()?->id);
        $this->dispatchRun($run, $a5, $context);

        return $run;
    }

    private function createRun(
        AllocationA5Run $a5,
        string $type,
        string $scope,
        array $parameters,
        ?int $actorId,
    ): ReportingExportRun {
        $disposition = $this->dispositions->snapshot($a5);
        return ReportingExportRun::query()->create([
            'module' => 'allocation_a6',
            'export_type' => $type,
            'scope' => $scope,
            'status' => 'queued',
            'phase' => 'QUEUED',
            'progress_percent' => 0,
            'progress_current' => 0,
            'progress_total' => 0,
            'progress_message' => 'Waiting for the centralized export queue.',
            'parameters' => $parameters,
            'source_snapshot' => [
                'allocation_a5_run_id' => (int) $a5->id,
                'allocation_a4_run_id' => (int) $a5->allocation_a4_run_id,
                'a4_output_hash' => (string) $a5->a4_output_hash,
                'a5_candidate_hash' => (string) $a5->candidate_result_hash,
                'a5_capacity_hash' => (string) $a5->capacity_result_hash,
                'a5_finalized_at' => $a5->finalized_at?->toIso8601String(),
                'disposition_revision' => $disposition['revision'],
                'disposition_hash' => $disposition['hash'],
            ],
            'generated_by' => $actorId,
            'queued_at' => now(),
        ]);
    }

    private function dispatchRun(ReportingExportRun $run, AllocationA5Run $a5, ExaminationContext $context): void
    {
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');
        ProcessAllocationA6Export::dispatch($examinationId, $run->id, $a5->id, $run->generated_by);
    }

    private function isDispositionSnapshotCurrent(ReportingExportRun $run): bool
    {
        $snapshot = (array) $run->source_snapshot;
        $hash = (string) ($snapshot['disposition_hash'] ?? '');
        $a5Id = (int) ($snapshot['allocation_a5_run_id'] ?? 0);
        if ($hash === '' || $a5Id < 1) return false;
        $a5 = AllocationA5Run::query()->find($a5Id);
        if (! $a5) return false;
        $current = $this->dispositions->snapshot($a5);
        return hash_equals($hash, (string) $current['hash']);
    }

    private function assertA6Run(ReportingExportRun $run): void
    {
        abort_unless($run->module === 'allocation_a6', 404);
    }
}
