<?php

namespace App\Http\Controllers;

use App\Enums\CircularProcessingStatus;
use App\Http\Requests\StoreCircularEntryRequest;
use App\Http\Requests\UpdateCircularEntryRequest;
use App\Http\Requests\UploadCircularImportRequest;
use App\Models\BachelorSubject;
use App\Models\CircularEntry;
use App\Models\CircularImportBatch;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\CircularProcessingState;
use App\Models\CircularProcessingAudit;
use App\Models\CircularAuthorityPreview;
use App\Models\CircularConfirmation;
use App\Models\PostRelatedSubject;
use App\Services\Circular\CircularDatasetService;
use App\Services\Circular\CircularFormOptions;
use App\Services\Circular\CircularSpreadsheetService;
use App\Services\Circular\CircularAuthorityWorkflowService;
use App\Services\Circular\CircularFinalizedDatasetService;
use App\Reports\Pdf\CircularFinalSummaryPdfReport;
use App\Reports\Excel\CircularFinalSummaryExcelReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;

final class CircularController extends Controller
{
    public function index(): View
    {
        $state = CircularProcessingState::query()->firstOrCreate(['id' => 1], ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]);
        $version = (int) $state->current_version;
        $query = CircularEntry::query()->where('version', $version);

        return view('circular.index', [
            'state' => $state,
            'counts' => [
                'entries' => (clone $query)->count(),
                'active' => (clone $query)->where('status', 'active')->count(),
                'main' => (clone $query)->whereNull('sub_cadre_code')->count(),
                'sub' => (clone $query)->whereNotNull('sub_cadre_code')->count(),
                'posts' => (int) (clone $query)->where('status', 'active')->sum('post_count'),
            ],
            'latestBatch' => CircularImportBatch::query()->latest('id')->first(),
        ]);
    }


    public function history(): View
    {
        $state = CircularProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]
        );

        $versions = CircularEntry::query()
            ->selectRaw('version, COUNT(*) as entry_count, SUM(CASE WHEN status = ? THEN post_count ELSE 0 END) as active_posts, MIN(created_at) as created_at, MAX(updated_at) as updated_at', ['active'])
            ->groupBy('version')
            ->orderByDesc('version')
            ->get();

        $audits = CircularProcessingAudit::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);

        return view('circular.history', compact('state', 'versions', 'audits'));
    }

    public function version(int $version): View
    {
        abort_if($version < 1, 404);

        $state = CircularProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]
        );

        $entries = CircularEntry::query()->with(['bachelorSubjects', 'prsSubjects'])
            ->where('version', $version)
            ->orderBy('cadre_type')->orderBy('cadre_serial')
            ->orderByRaw('sub_serial IS NULL DESC')->orderBy('sub_serial')->orderBy('id')->get();

        abort_if($entries->isEmpty(), 404);

        $bachelorMap = BachelorSubject::query()
            ->whereIn('subject_code', $entries->flatMap(fn ($e) => $e->bachelorSubjects->pluck('subject_code'))->unique())
            ->pluck('subject_name', 'subject_code');
        $prsMap = PostRelatedSubject::query()
            ->whereIn('subject_code', $entries->flatMap(fn ($e) => $e->prsSubjects->pluck('prs_code'))->unique())
            ->pluck('subject_name', 'subject_code');

        $isHistorical = $version !== (int) $state->current_version;

        return view('circular.view', compact('state', 'version', 'entries', 'bachelorMap', 'prsMap', 'isHistorical'));
    }

    public function entries(Request $request): View
    {
        $state = CircularProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]
        );

        $availableVersions = CircularEntry::query()
            ->select('version')->distinct()->orderByDesc('version')->pluck('version');

        $version = (int) ($request->integer('version') ?: $state->current_version);
        if ($version > 0 && $availableVersions->isNotEmpty() && ! $availableVersions->contains($version)) {
            abort(404);
        }

        $query = CircularEntry::query()
            ->with(['bachelorSubjects', 'prsSubjects'])
            ->where('version', $version);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($search, $like): void {
                $builder->where('cadre_name_snapshot', 'like', $like)
                    ->orWhere('cadre_name_bn_snapshot', 'like', $like)
                    ->orWhere('post_name_snapshot', 'like', $like)
                    ->orWhere('post_name_bn_snapshot', 'like', $like)
                    ->orWhere('note', 'like', $like);

                if (ctype_digit($search)) {
                    $code = (int) $search;
                    $builder->orWhere('cadre_code', $code)
                        ->orWhere('sub_cadre_code', $code)
                        ->orWhere('effective_code', $code);
                }
            });
        }

        $type = strtoupper((string) $request->query('cadre_type', ''));
        if (in_array($type, ['GG', 'TT'], true)) {
            $query->where('cadre_type', $type);
        }

        $status = strtolower((string) $request->query('status', ''));
        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        $source = strtolower((string) $request->query('source', ''));
        if (in_array($source, ['excel', 'ui'], true)) {
            $query->where('source', $source);
        }

        $bachelorCode = trim((string) $request->query('bachelor_subject_code', ''));
        if ($bachelorCode !== '') {
            $query->whereHas('bachelorSubjects', fn ($builder) => $builder->where('subject_code', $bachelorCode));
        }

        $prsCode = trim((string) $request->query('prs_code', ''));
        if ($prsCode !== '') {
            $query->whereHas('prsSubjects', fn ($builder) => $builder->where('prs_code', $prsCode));
        }

        $filtered = clone $query;
        $summary = [
            'entries' => (clone $filtered)->count(),
            'active_posts' => (int) (clone $filtered)->where('status', 'active')->sum('post_count'),
        ];

        $entries = $query
            ->orderBy('cadre_type')
            ->orderBy('cadre_serial')
            ->orderByRaw('sub_serial IS NULL DESC')
            ->orderBy('sub_serial')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $bachelorMap = BachelorSubject::query()
            ->whereIn('subject_code', $entries->getCollection()->flatMap(fn ($entry) => $entry->bachelorSubjects->pluck('subject_code'))->unique())
            ->pluck('subject_name', 'subject_code');
        $prsMap = PostRelatedSubject::query()
            ->whereIn('subject_code', $entries->getCollection()->flatMap(fn ($entry) => $entry->prsSubjects->pluck('prs_code'))->unique())
            ->pluck('subject_name', 'subject_code');

        return view('circular.entries-index', [
            'state' => $state,
            'version' => $version,
            'availableVersions' => $availableVersions,
            'entries' => $entries,
            'summary' => $summary,
            'bachelorMap' => $bachelorMap,
            'prsMap' => $prsMap,
            'isHistorical' => $version !== (int) $state->current_version,
        ]);
    }

    public function show(CircularEntry $entry): View
    {
        $state = CircularProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]
        );

        $entry->load(['bachelorSubjects', 'prsSubjects']);

        $bachelorMap = BachelorSubject::query()
            ->whereIn('subject_code', $entry->bachelorSubjects->pluck('subject_code')->unique())
            ->pluck('subject_name', 'subject_code');
        $prsMap = PostRelatedSubject::query()
            ->whereIn('subject_code', $entry->prsSubjects->pluck('prs_code')->unique())
            ->pluck('subject_name', 'subject_code');

        $masterCadre = CadreMaster::query()->where('cadre_code', $entry->cadre_code)->first();
        $masterSubCadre = $entry->sub_cadre_code === null
            ? null
            : CadreSubMaster::query()->with('parentCadre')->where('sub_cadre_code', $entry->sub_cadre_code)->first();

        return view('circular.entry-show', [
            'state' => $state,
            'entry' => $entry,
            'bachelorMap' => $bachelorMap,
            'prsMap' => $prsMap,
            'masterCadre' => $masterCadre,
            'masterSubCadre' => $masterSubCadre,
            'isHistorical' => (int) $entry->version !== (int) $state->current_version,
        ]);
    }

    public function template(CircularSpreadsheetService $spreadsheets): BinaryFileResponse
    {
        return $spreadsheets->template();
    }

    public function upload(UploadCircularImportRequest $request, CircularSpreadsheetService $spreadsheets): RedirectResponse
    {
        try {
            $batch = $spreadsheets->stage($request->file('file'), (int) $request->user()->id);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()->route('circular.import.review', $batch);
    }

    public function review(CircularImportBatch $batch): View
    {
        return view('circular.import-review', [
            'batch' => $batch,
            'rows' => $batch->rows()->orderBy('row_number')->paginate(100),
        ]);
    }

    public function approve(Request $request, CircularImportBatch $batch, CircularDatasetService $datasets): RedirectResponse
    {
        $validated = $request->validate(['approval_note' => ['nullable', 'string', 'max:3000']]);
        try {
            $version = $datasets->approveImport($batch, $request->user(), $validated['approval_note'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('circular.view')->with('success', "Circular import approved as version {$version}.");
    }

    public function approveDraft(Request $request, CircularDatasetService $datasets): RedirectResponse
    {
        $validated = $request->validate([
            'approval_note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        try {
            $version = $datasets->approveCurrentDraft($request->user(), $validated['approval_note']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('circular.index')
            ->with('success', "Circular version {$version} approved as the current effective dataset. Downstream stale stages still require regeneration.");
    }

    public function create(CircularFormOptions $options): View
    {
        return view('circular.create', $options->get());
    }

    public function store(StoreCircularEntryRequest $request, CircularDatasetService $datasets): RedirectResponse
    {
        $input = $request->validated();
        $reason = $input['correction_reason'];
        unset($input['correction_reason']);
        $input['cadre_type'] = null;
        $datasets->createManual($input, $request->user(), $reason);

        return redirect()->route('circular.view')->with('success', 'Circular entry created from Master Data and audited.');
    }

    public function edit(CircularEntry $entry, CircularFormOptions $options): View
    {
        $state = CircularProcessingState::query()->firstOrCreate(['id' => 1], ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]);
        abort_unless($entry->version === (int) $state->current_version, 404);
        $data = $options->get();
        $data['entry'] = $entry->load('bachelorSubjects', 'prsSubjects');
        return view('circular.edit', $data);
    }

    public function update(UpdateCircularEntryRequest $request, CircularEntry $entry, CircularDatasetService $datasets): RedirectResponse
    {
        $data = $request->validated();
        $reason = $data['correction_reason'];
        unset($data['correction_reason']);
        $data['cadre_type'] = null;
        $result = $datasets->updateManual($entry, $data, $request->user(), $reason);

        if (! $result['changed']) {
            return redirect()->route('circular.view')->with('info', 'No actual Circular change was detected. No new version or audit event was created.');
        }

        return redirect()->route('circular.view')->with('success', 'Circular entry updated and audited.');
    }

    public function destroy(Request $request, CircularEntry $entry, CircularDatasetService $datasets): RedirectResponse
    {
        $validated = $request->validate(['correction_reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $datasets->deleteManual($entry, $request->user(), $validated['correction_reason']);
        return redirect()->route('circular.view')->with('success', 'Circular entry deleted and audited.');
    }

    public function authority(CircularAuthorityWorkflowService $workflow): View
    {
        $state = CircularProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]
        );

        $previews = CircularAuthorityPreview::query()
            ->with('confirmations')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->get();

        $currentHash = (int) $state->current_version > 0
            ? $workflow->datasetHash((int) $state->current_version)
            : '';

        return view('circular.authority', compact('state', 'previews', 'currentHash'));
    }

    public function generateAuthorityPreview(Request $request, CircularAuthorityWorkflowService $workflow): RedirectResponse
    {
        try {
            $preview = $workflow->generate($request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('circular.authority.index')
            ->with('success', "Authority Preview #{$preview->id} generated for Circular version {$preview->version}.");
    }

    public function downloadAuthorityPreview(CircularAuthorityPreview $preview): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($preview->file_path), 404, 'Authority Preview PDF file not found.');

        return Storage::disk('local')->download(
            $preview->file_path,
            'circular-authority-preview-v'.$preview->version.'-preview-'.$preview->id.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function confirmAuthorityPreview(
        Request $request,
        CircularAuthorityPreview $preview,
        CircularAuthorityWorkflowService $workflow,
    ): RedirectResponse {
        $validated = $request->validate([
            'confirmation_notes' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        try {
            $workflow->confirm($preview, $request->user(), $validated['confirmation_notes']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('circular.authority.index')
            ->with('success', "Authority Preview #{$preview->id} confirmed for Circular version {$preview->version}.");
    }

    public function finalizeCircular(Request $request, CircularAuthorityWorkflowService $workflow): RedirectResponse
    {
        try {
            $version = $workflow->finalize($request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('circular.authority.index')
            ->with('success', "Circular version {$version} finalized and is now authoritative for downstream eligibility processing.");
    }

    public function finalReport(CircularFinalizedDatasetService $dataset): View
    {
        $state = $dataset->state();
        $summary = $dataset->summary();
        $entries = $summary['ready'] ? $dataset->entries() : collect();

        return view('circular.final-report', compact('state', 'summary', 'entries'));
    }

    public function finalReportPdf(CircularFinalSummaryPdfReport $report): StreamedResponse
    {
        try {
            $file = $report->generate();
        } catch (ValidationException $exception) {
            abort(422, collect($exception->errors())->flatten()->first() ?? 'Finalized Circular is required.');
        }

        return response()->streamDownload(
            static function () use ($file): void { echo $file['content']; },
            $file['filename'],
            ['Content-Type' => 'application/pdf']
        );
    }

    public function finalReportExcel(CircularFinalSummaryExcelReport $report): StreamedResponse
    {
        try {
            $file = $report->generate();
        } catch (ValidationException $exception) {
            abort(422, collect($exception->errors())->flatten()->first() ?? 'Finalized Circular is required.');
        }

        return response()->streamDownload(
            static function () use ($file): void { echo $file['content']; },
            $file['filename'],
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function view(): View
    {
        $state = CircularProcessingState::query()->firstOrCreate(['id' => 1], ['status' => CircularProcessingStatus::NotStarted, 'current_version' => 0]);
        $version = (int) $state->current_version;
        $entries = CircularEntry::query()->with(['bachelorSubjects', 'prsSubjects'])
            ->where('version', $version)
            ->orderBy('cadre_type')->orderBy('cadre_serial')->orderByRaw('sub_serial IS NULL DESC')->orderBy('sub_serial')->orderBy('id')->get();

        $bachelorMap = BachelorSubject::query()->whereIn('subject_code', $entries->flatMap(fn ($e) => $e->bachelorSubjects->pluck('subject_code'))->unique())
            ->pluck('subject_name', 'subject_code');
        $prsMap = PostRelatedSubject::query()->whereIn('subject_code', $entries->flatMap(fn ($e) => $e->prsSubjects->pluck('prs_code'))->unique())
            ->pluck('subject_name', 'subject_code');

        $isHistorical = false;
        return view('circular.view', compact('state', 'version', 'entries', 'bachelorMap', 'prsMap', 'isHistorical'));
    }
}
