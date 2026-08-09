<?php

namespace App\Http\Controllers;

use App\Enums\CircularProcessingStatus;
use App\Http\Requests\StoreCircularEntryRequest;
use App\Http\Requests\UpdateCircularEntryRequest;
use App\Http\Requests\UploadCircularImportRequest;
use App\Models\BachelorSubject;
use App\Models\CircularEntry;
use App\Models\CircularImportBatch;
use App\Models\CircularProcessingState;
use App\Models\PostRelatedSubject;
use App\Services\Circular\CircularDatasetService;
use App\Services\Circular\CircularFormOptions;
use App\Services\Circular\CircularSpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function create(CircularFormOptions $options): View
    {
        return view('circular.create', $options->get());
    }

    public function store(StoreCircularEntryRequest $request, CircularDatasetService $datasets): RedirectResponse
    {
        $input = $request->validated();
        $input['cadre_type'] = null;
        $datasets->createManual($input, $request->user());
        return redirect()->route('circular.view')->with('success', 'Circular entry created from Master Data.');
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
        $datasets->updateManual($entry, $data, $request->user(), $reason);
        return redirect()->route('circular.view')->with('success', 'Circular entry updated and audited.');
    }

    public function destroy(Request $request, CircularEntry $entry, CircularDatasetService $datasets): RedirectResponse
    {
        $validated = $request->validate(['correction_reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $datasets->deleteManual($entry, $request->user(), $validated['correction_reason']);
        return redirect()->route('circular.view')->with('success', 'Circular entry deleted and audited.');
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

        return view('circular.view', compact('state', 'version', 'entries', 'bachelorMap', 'prsMap'));
    }
}
