@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Previous BCS Dataset — Full View</h2>
                <div class="text-secondary mt-1">
                    BCS {{ $dataset->repository->bcs_number }} · Version {{ $dataset->version }} · {{ $dataset->original_name }}
                </div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('previous-bcs-repository.datasets.show', $dataset) }}">Back to Dataset</a>
                <a class="btn btn-outline-secondary" href="{{ route('previous-bcs-repository.index') }}">Repository</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="text-secondary small">Status</div>
                <div class="h3 mb-0">{{ strtoupper(str_replace('_', ' ', $dataset->status)) }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="text-secondary small">Total Rows</div>
                <div class="h3 mb-0">{{ number_format($rows->count()) }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="text-secondary small">Warning Rows</div>
                <div class="h3 mb-0">{{ number_format($warningRows) }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="text-secondary small">Invalid Rows</div>
                <div class="h3 mb-0">{{ number_format($invalidRows) }}</div>
            </div></div>
        </div>
    </div>

    @if($dataset->dataset_hash)
        <div class="alert alert-info">
            <strong>Dataset Hash:</strong> <code>{{ $dataset->dataset_hash }}</code>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Complete Historical Dataset</h3>
                <div class="card-subtitle">
                    Read-only historical data. Corrections must be supplied as a new versioned Excel/CSV dataset.
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter table-nowrap card-table">
                <thead>
                <tr>
                    <th>Row</th>
                    <th>Reg</th>
                    <th>Name</th>
                    <th>Father</th>
                    <th>Mother</th>
                    <th>b_date</th>
                    <th>dob</th>
                    <th>District</th>
                    <th>SSC Roll</th>
                    <th>SSC Year</th>
                    <th>HSC Roll</th>
                    <th>HSC Year</th>
                    <th>NID</th>
                    <th>Cadre</th>
                    <th>System Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->source_row }}</td>
                        <td><code>{{ $row->reg ?: '—' }}</code></td>
                        <td>{{ $row->name ?: '—' }}</td>
                        <td>{{ $row->fname ?: '—' }}</td>
                        <td>{{ $row->mname ?: '—' }}</td>
                        <td>
                            <div>{{ $row->b_date?->format('d-m-Y') ?: '—' }}</div>
                            @if($row->b_date_raw)<div class="small text-secondary">Raw: {{ $row->b_date_raw }}</div>@endif
                        </td>
                        <td>
                            <div>{{ $row->dob?->format('d-m-Y') ?: '—' }}</div>
                            @if($row->dob_raw)<div class="small text-secondary">Raw: {{ $row->dob_raw }}</div>@endif
                        </td>
                        <td>{{ $row->dist_name ?: '—' }}</td>
                        <td><code>{{ $row->ssc_roll ?: '—' }}</code></td>
                        <td>{{ $row->ssc_year ?: '—' }}</td>
                        <td><code>{{ $row->hsc_roll ?: '—' }}</code></td>
                        <td>{{ $row->hsc_year ?: '—' }}</td>
                        <td><code>{{ $row->nid_no ?: '—' }}</code></td>
                        <td><code>{{ $row->cadre ?: '—' }}</code></td>
                        <td>
                            @php
                                $badge = match($row->validation_status) {
                                    'valid' => 'bg-green-lt',
                                    'ready_for_validation' => 'bg-blue-lt',
                                    'invalid' => 'bg-red-lt',
                                    default => 'bg-secondary-lt',
                                };
                            @endphp

                            <span class="badge {{ $badge }}">
                                {{ strtoupper(str_replace('_', ' ', $row->validation_status)) }}
                            </span>

                            @if($row->validation_warnings)
                                <span class="badge bg-yellow-lt ms-1">WARNING</span>
                            @endif

                            @if($row->validation_errors || $row->validation_warnings)
                                <details class="small mt-1">
                                    <summary style="cursor:pointer">View system details</summary>
                                    @foreach((array)$row->validation_errors as $error)
                                        <div class="text-danger mt-1">
                                            <strong>{{ $error['code'] ?? 'ERROR' }}</strong>
                                            @if(!empty($error['message'])) — {{ $error['message'] }} @endif
                                        </div>
                                    @endforeach
                                    @foreach((array)$row->validation_warnings as $warning)
                                        <div class="text-warning mt-1">
                                            <strong>{{ $warning['code'] ?? 'WARNING' }}</strong>
                                            @if(!empty($warning['message'])) — {{ $warning['message'] }} @endif
                                        </div>
                                    @endforeach
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="text-center text-secondary py-5">No dataset rows available.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection
