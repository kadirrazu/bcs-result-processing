@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Previous BCS Recommendation Repository</h2>
                <div class="text-secondary mt-1">Central reusable repository shared by all BCS examination workspaces.</div>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-primary" href="{{ route('previous-bcs-repository.search') }}">Consolidated Candidate Search</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Upload Dataset</h3></div>
                <form method="POST" action="{{ route('previous-bcs-repository.datasets.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">BCS Number</label>
                            <input type="number" min="1" max="999" name="bcs_number" value="{{ old('bcs_number') }}" class="form-control @error('bcs_number') is-invalid @enderror" required>
                            <div class="form-hint">BCS number identifies the repository. Re-upload creates the next version; history is preserved.</div>
                            @error('bcs_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label">Excel / CSV Dataset</label>
                            <input type="file" name="file" accept=".xlsx,.csv" class="form-control @error('file') is-invalid @enderror" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" type="submit">Upload & Queue Staging</button>
                    </div>
                </form>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Exact Excel columns</div>
                    <div class="small text-secondary text-break">
                        <code>reg, name, fname, mname, b_date, dob, dist_name, ssc_roll, ssc_year, hsc_roll, hsc_year, nid_no, cadre</code>
                    </div>
                    <div class="small mt-3">
                        Optional: <code>fname</code>, <code>mname</code>, <code>dob</code>, <code>dist_name</code>, <code>nid_no</code>.
                    </div>
                    <div class="small text-secondary mt-2">
                        <code>b_date</code> accepts DDMMYY or DDMMYYYY. <code>dob</code> is optional secondary DOB evidence.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Repository</h3>
                    <div class="ms-auto">
                        <form method="GET" class="d-flex gap-2">
                            <input class="form-control form-control-sm" name="search" value="{{ $search }}" placeholder="BCS number">
                            <button class="btn btn-sm btn-outline-primary">Search</button>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr><th>BCS</th><th>Versions</th><th>Latest Dataset</th><th>Effective</th><th>Rows</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        @forelse($repositories as $repository)
                            @php $latest = $repository->datasets->first(); @endphp
                            <tr>
                                <td><strong>{{ $repository->bcs_number }}</strong></td>
                                <td>{{ $repository->datasets->count() }}</td>
                                <td>{{ $latest ? 'v'.$latest->version : '—' }}</td>
                                <td>
                                    @if($repository->currentEffectiveDataset)
                                        <span class="badge bg-green-lt">v{{ $repository->currentEffectiveDataset->version }}</span>
                                    @else
                                        <span class="text-secondary">None</span>
                                    @endif
                                </td>
                                <td>{{ $latest ? number_format($latest->total_rows) : '—' }}</td>
                                <td>
                                    @if($latest)
                                        @php
                                            $statusBadge = match($latest->status) {
                                                'effective' => 'bg-green-lt',
                                                'validated' => 'bg-blue-lt',
                                                'staged' => 'bg-azure-lt',
                                                'queued', 'processing', 'validation_queued', 'validating' => 'bg-yellow-lt',
                                                'superseded' => 'bg-secondary-lt',
                                                'failed', 'validation_failed' => 'bg-red-lt',
                                                default => 'bg-secondary-lt',
                                            };
                                        @endphp
                                        <span class="badge {{ $statusBadge }}">
                                            {{ strtoupper(str_replace('_',' ',$latest->status)) }}
                                        </span>
                                    @else —
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($latest)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('previous-bcs-repository.datasets.show',$latest) }}">Open</a>
                                    @endif
                                </td>
                            </tr>
                            @if($repository->datasets->count() > 1)
                                <tr>
                                    <td></td>
                                    <td colspan="6" class="small text-secondary">
                                        Versions:
                                        @foreach($repository->datasets as $dataset)
                                            <a class="me-2" href="{{ route('previous-bcs-repository.datasets.show',$dataset) }}">v{{ $dataset->version }}</a>
                                        @endforeach
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary py-5">No Previous BCS repository dataset uploaded yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($repositories->hasPages())<div class="card-footer">{{ $repositories->links() }}</div>@endif
            </div>
        </div>
    </div>
</div>
</div>
@endsection
