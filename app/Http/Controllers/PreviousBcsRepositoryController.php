<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePreviousBcsRepositoryDatasetRequest;
use App\Models\PreviousBcsRepository;
use App\Jobs\ProcessPreviousBcsRepositoryValidation;
use App\Models\PreviousBcsRepositoryDataset;
use App\Models\PreviousBcsRepositoryRow;
use App\Services\PreviousBcsRepository\PreviousBcsRepositoryAuthorityService;
use App\Services\PreviousBcsRepository\PreviousBcsRepositoryAuditService;
use App\Services\PreviousBcsRepository\PreviousBcsRepositoryImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PreviousBcsRepositoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $repositories = PreviousBcsRepository::query()
            ->with([
                'datasets' => fn ($query) => $query->latest('version'),
                'currentEffectiveDataset',
            ])
            ->when($search !== '', fn ($query) => $query->where('bcs_number', 'like', '%'.$search.'%'))
            ->orderByDesc('bcs_number')
            ->paginate(20)
            ->withQueryString();

        return view('previous-bcs-repository.index', compact('repositories', 'search'));
    }


    public function search(Request $request): View
    {
        $filters = [
            'name' => trim((string) $request->query('name')),
            'reg' => trim((string) $request->query('reg')),
            'bcs_number' => trim((string) $request->query('bcs_number')),
            'cadre' => trim((string) $request->query('cadre')),
            'district' => trim((string) $request->query('district')),
        ];

        $hasFilter = collect($filters)->contains(fn ($value) => $value !== '');

        $rows = PreviousBcsRepositoryRow::query()
            ->select([
                'previous_bcs_repository_rows.*',
                'repo.bcs_number as repository_bcs_number',
                'ds.version as repository_dataset_version',
            ])
            ->join('previous_bcs_repository_datasets as ds', 'ds.id', '=', 'previous_bcs_repository_rows.dataset_id')
            ->join('previous_bcs_repositories as repo', function ($join): void {
                $join->on('repo.id', '=', 'ds.repository_id')
                    ->on('repo.current_effective_dataset_id', '=', 'ds.id');
            })
            ->when(! $hasFilter, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($filters['name'] !== '', fn ($query) => $query->where('previous_bcs_repository_rows.name', 'like', '%'.$filters['name'].'%'))
            ->when($filters['reg'] !== '', fn ($query) => $query->where('previous_bcs_repository_rows.reg', 'like', '%'.$filters['reg'].'%'))
            ->when($filters['bcs_number'] !== '', fn ($query) => $query->where('repo.bcs_number', (int) $filters['bcs_number']))
            ->when($filters['cadre'] !== '', fn ($query) => $query->where('previous_bcs_repository_rows.cadre', 'like', '%'.$filters['cadre'].'%'))
            ->when($filters['district'] !== '', fn ($query) => $query->where('previous_bcs_repository_rows.dist_name', 'like', '%'.$filters['district'].'%'))
            ->orderByDesc('repo.bcs_number')
            ->orderBy('previous_bcs_repository_rows.name')
            ->orderBy('previous_bcs_repository_rows.reg')
            ->paginate(50)
            ->withQueryString();

        $bcsNumbers = PreviousBcsRepository::query()
            ->whereNotNull('current_effective_dataset_id')
            ->orderByDesc('bcs_number')
            ->pluck('bcs_number');

        return view('previous-bcs-repository.search', compact('rows', 'filters', 'hasFilter', 'bcsNumbers'));
    }

    public function store(
        StorePreviousBcsRepositoryDatasetRequest $request,
        PreviousBcsRepositoryImportService $service,
    ): RedirectResponse {
        $dataset = $service->enqueue(
            (int) $request->validated('bcs_number'),
            $request->file('file'),
            (int) $request->user()->getAuthIdentifier(),
        );

        return redirect()
            ->route('previous-bcs-repository.datasets.show', $dataset)
            ->with('success', "BCS {$dataset->repository->bcs_number} dataset v{$dataset->version} queued for staging.");
    }

    public function show(Request $request, PreviousBcsRepositoryDataset $dataset): View
    {
        $dataset->load('repository');

        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status', 'all'));
        $cadre = trim((string) $request->query('cadre'));
        $sscYear = trim((string) $request->query('ssc_year'));
        $hscYear = trim((string) $request->query('hsc_year'));

        $rowsQuery = $dataset->rows();

        if ($search !== '') {
            $rowsQuery->where(function ($query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where('reg', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('fname', 'like', $like)
                    ->orWhere('mname', 'like', $like)
                    ->orWhere('dist_name', 'like', $like)
                    ->orWhere('ssc_roll', 'like', $like)
                    ->orWhere('hsc_roll', 'like', $like)
                    ->orWhere('nid_no', 'like', $like)
                    ->orWhere('cadre', 'like', $like)
                    ->orWhereRaw('CAST(ssc_year AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(hsc_year AS CHAR) LIKE ?', [$like]);
            });
        }

        if ($status === 'warning') {
            $rowsQuery->whereNotNull('validation_warnings');
        } elseif ($status !== 'all' && $status !== '') {
            $rowsQuery->where('validation_status', $status);
        }

        if ($cadre !== '') {
            $rowsQuery->where('cadre', $cadre);
        }

        if ($sscYear !== '') {
            $rowsQuery->where('ssc_year', (int) $sscYear);
        }

        if ($hscYear !== '') {
            $rowsQuery->where('hsc_year', (int) $hscYear);
        }

        $rows = $rowsQuery
            ->orderBy('source_row')
            ->paginate(50)
            ->withQueryString();

        $cadreOptions = $dataset->rows()
            ->whereNotNull('cadre')
            ->where('cadre', '<>', '')
            ->distinct()
            ->orderBy('cadre')
            ->pluck('cadre');

        $sscYearOptions = $dataset->rows()
            ->whereNotNull('ssc_year')
            ->distinct()
            ->orderByDesc('ssc_year')
            ->pluck('ssc_year');

        $hscYearOptions = $dataset->rows()
            ->whereNotNull('hsc_year')
            ->distinct()
            ->orderByDesc('hsc_year')
            ->pluck('hsc_year');

        return view('previous-bcs-repository.show', compact(
            'dataset',
            'rows',
            'search',
            'status',
            'cadre',
            'sscYear',
            'hscYear',
            'cadreOptions',
            'sscYearOptions',
            'hscYearOptions',
        ));
    }

    public function validateDataset(
        Request $request,
        PreviousBcsRepositoryDataset $dataset,
        PreviousBcsRepositoryAuditService $audit,
    ): RedirectResponse {
        abort_unless(
            in_array($dataset->status, ['staged', 'validation_failed', 'validated'], true),
            409,
            'Only a staged/failed/validated dataset can be queued for validation.'
        );

        $dataset->update([
            'status' => 'validation_queued',
            'processed_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'dataset_hash' => null,
            'validated_at' => null,
            'validated_by' => null,
            'finished_at' => null,
        ]);

        $audit->record(
            'DATASET_VALIDATION_QUEUED',
            $dataset->repository_id,
            $dataset->id,
            (int) $request->user()->getAuthIdentifier(),
        );

        ProcessPreviousBcsRepositoryValidation::dispatch(
            (int) $dataset->id,
            (int) $request->user()->getAuthIdentifier(),
        );

        return redirect()->route('previous-bcs-repository.datasets.show', $dataset)
            ->with('success', 'Previous BCS dataset validation queued.');
    }

    public function makeEffective(
        Request $request,
        PreviousBcsRepositoryDataset $dataset,
        PreviousBcsRepositoryAuthorityService $authority,
    ): RedirectResponse {
        $validated = $request->validate([
            'confirmation' => ['required', 'in:EFFECTIVE'],
        ], [
            'confirmation.in' => 'Type EFFECTIVE to confirm this repository version.',
        ]);

        $authority->makeEffective(
            (int) $dataset->id,
            (int) $request->user()->getAuthIdentifier(),
        );

        return redirect()->route('previous-bcs-repository.datasets.show', $dataset)
            ->with('success', "BCS {$dataset->repository->bcs_number} dataset v{$dataset->version} is now the effective repository version.");
    }

    public function rowDetail(
        PreviousBcsRepositoryDataset $dataset,
        \App\Models\PreviousBcsRepositoryRow $row,
    ): View {
        abort_unless((int) $row->dataset_id === (int) $dataset->id, 404);

        $dataset->load('repository');

        return view('previous-bcs-repository.row-detail', compact('dataset', 'row'));
    }

    public function detail(PreviousBcsRepositoryDataset $dataset): View
    {
        $dataset->load('repository');

        $rows = $dataset->rows()
            ->orderBy('source_row')
            ->get();

        $warningRows = $rows->filter(fn ($row) => ! empty($row->validation_warnings))->count();
        $invalidRows = $rows->filter(fn ($row) => $row->validation_status === 'invalid')->count();

        return view('previous-bcs-repository.detail', compact(
            'dataset',
            'rows',
            'warningRows',
            'invalidRows',
        ));
    }

    public function status(PreviousBcsRepositoryDataset $dataset): JsonResponse
    {
        return response()->json([
            'status' => $dataset->status,
            'running' => in_array($dataset->status, ['queued', 'processing', 'validation_queued', 'validating'], true),
            'total_rows' => $dataset->total_rows,
            'processed_rows' => $dataset->processed_rows,
            'staged_rows' => $dataset->staged_rows,
            'valid_rows' => $dataset->valid_rows,
            'invalid_rows' => $dataset->invalid_rows,
            'progress_percent' => (float) $dataset->progress_percent,
            'failure_message' => $dataset->failure_message,
        ]);
    }
}
