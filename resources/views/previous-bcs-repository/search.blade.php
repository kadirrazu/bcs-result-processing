@extends('layouts.app')

@section('title', 'Previous BCS Consolidated Search')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <div class="page-pretitle">Previous BCS Recommendation Repository</div>
                <h2 class="page-title">Consolidated Candidate Search</h2>
                <div class="text-secondary mt-1">Search across the current effective dataset of every Previous BCS repository.</div>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="{{ route('previous-bcs-repository.index') }}">Back to Repository</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('previous-bcs-repository.search') }}" class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label">Candidate Name</label>
                    <input class="form-control" name="name" value="{{ $filters['name'] }}" placeholder="Search by candidate name">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label">Registration</label>
                    <input class="form-control" name="reg" value="{{ $filters['reg'] }}" placeholder="Reg">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label">Previous BCS</label>
                    <select class="form-select" name="bcs_number">
                        <option value="">All BCS</option>
                        @foreach($bcsNumbers as $bcsNumber)
                            <option value="{{ $bcsNumber }}" @selected((string)$filters['bcs_number'] === (string)$bcsNumber)>BCS {{ $bcsNumber }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label">Cadre</label>
                    <input class="form-control" name="cadre" value="{{ $filters['cadre'] }}" placeholder="e.g. ADMN">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label">District</label>
                    <input class="form-control" name="district" value="{{ $filters['district'] }}" placeholder="District">
                </div>
                <div class="col-auto"><button class="btn btn-primary">Search</button></div>
                <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('previous-bcs-repository.search') }}">Clear</a></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Matching Effective Repository Records</h3>
                <div class="card-subtitle">
                    @if($hasFilter)
                        {{ number_format($rows->total()) }} matching record(s). Historical superseded dataset versions are excluded.
                    @else
                        Enter at least one search/filter value to search the consolidated repository.
                    @endif
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-nowrap">
                <thead>
                <tr>
                    <th>BCS</th>
                    <th>Reg / Name</th>
                    <th>Primary DOB</th>
                    <th>Secondary DOB</th>
                    <th>District</th>
                    <th>SSC</th>
                    <th>HSC</th>
                    <th>Cadre</th>
                    <th>Dataset</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><strong>{{ $row->repository_bcs_number }}</strong></td>
                        <td>
                            <div><code>{{ $row->reg ?: '—' }}</code></div>
                            <div class="small">{{ $row->name ?: '—' }}</div>
                            @if($row->fname)<div class="small text-secondary">Father: {{ $row->fname }}</div>@endif
                        </td>
                        <td>
                            <div>{{ $row->b_date?->format('d M Y') ?: '—' }}</div>
                            <div class="small text-secondary">Raw: {{ $row->b_date_raw ?: '—' }}</div>
                        </td>
                        <td>
                            <div>{{ $row->dob?->format('d M Y') ?: '—' }}</div>
                            <div class="small text-secondary">Raw: {{ $row->dob_raw ?: '—' }}</div>
                        </td>
                        <td>{{ $row->dist_name ?: '—' }}</td>
                        <td>
                            <div><span class="text-secondary small">Roll:</span> <code>{{ $row->ssc_roll ?: '—' }}</code></div>
                            <div><span class="text-secondary small">Year:</span> {{ $row->ssc_year ?: '—' }}</div>
                        </td>
                        <td>
                            <div><span class="text-secondary small">Roll:</span> <code>{{ $row->hsc_roll ?: '—' }}</code></div>
                            <div><span class="text-secondary small">Year:</span> {{ $row->hsc_year ?: '—' }}</div>
                        </td>
                        <td><code>{{ $row->cadre ?: '—' }}</code></td>
                        <td>v{{ $row->repository_dataset_version }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('previous-bcs-repository.datasets.rows.show', [$row->dataset_id, $row->id]) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-secondary py-5">
                            {{ $hasFilter ? 'No matching effective Previous BCS repository record found.' : 'Search results will appear here.' }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="card-footer app-table-footer">
                <div class="app-table-summary">Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }}</div>
                {{ $rows->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</div>
</div>
@endsection
