@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center g-2">
            <div class="col">
                <h2 class="page-title">Previous BCS Row Detail</h2>
                <div class="text-secondary mt-1">
                    BCS {{ $dataset->repository->bcs_number }} · Version {{ $dataset->version }} · Source Row {{ $row->source_row }}
                </div>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-outline-secondary" href="{{ route('previous-bcs-repository.datasets.show', $dataset) }}">Back to Dataset</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title">Historical Recommendation Record</h3>
                <div class="card-subtitle">Read-only source record. Corrections are handled only through a new versioned dataset upload.</div>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach([
                    ['Registration', $row->reg ?: '—'],
                    ['Name', $row->name ?: '—'],
                    ['Father Name', $row->fname ?: '—'],
                    ['Mother Name', $row->mname ?: '—'],
                    ['District', $row->dist_name ?: '—'],
                    ['SSC Roll', $row->ssc_roll ?: '—'],
                    ['SSC Year', $row->ssc_year ?: '—'],
                    ['HSC Roll', $row->hsc_roll ?: '—'],
                    ['HSC Year', $row->hsc_year ?: '—'],
                    ['NID', $row->nid_no ?: '—'],
                    ['Cadre', $row->cadre ?: '—'],
                ] as [$label, $value])
                    <div class="col-sm-6 col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-secondary small">{{ $label }}</div>
                            <div class="fw-semibold mt-1 text-break">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach

                <div class="col-sm-6 col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-secondary small">Primary DOB (b_date)</div>
                        <div class="fw-semibold mt-1">{{ $row->b_date?->format('d-m-Y') ?: '—' }}</div>
                        <div class="small text-secondary">Raw: {{ $row->b_date_raw ?: '—' }}</div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-secondary small">Secondary DOB (dob)</div>
                        <div class="fw-semibold mt-1">{{ $row->dob?->format('d-m-Y') ?: '—' }}</div>
                        <div class="small text-secondary">Raw: {{ $row->dob_raw ?: '—' }}</div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-secondary small">Validation Status</div>
                        @php
                            $badge = match($row->validation_status) {
                                'valid' => 'bg-green-lt',
                                'ready_for_validation' => 'bg-blue-lt',
                                'invalid' => 'bg-red-lt',
                                default => 'bg-secondary-lt',
                            };
                        @endphp
                        <div class="mt-2">
                            <span class="badge {{ $badge }}">{{ strtoupper(str_replace('_', ' ', $row->validation_status)) }}</span>
                            @if($row->validation_warnings)
                                <span class="badge bg-yellow-lt ms-1">WARNING</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($row->validation_errors || $row->validation_warnings)
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">System Validation Details</h3></div>
            <div class="card-body">
                @foreach((array) $row->validation_errors as $error)
                    <div class="alert alert-danger py-2 mb-2">
                        <strong>{{ $error['code'] ?? 'ERROR' }}</strong>
                        @if(!empty($error['field'])) · {{ $error['field'] }} @endif
                        @if(!empty($error['message'])) — {{ $error['message'] }} @endif
                    </div>
                @endforeach
                @foreach((array) $row->validation_warnings as $warning)
                    <div class="alert alert-warning py-2 mb-2">
                        <strong>{{ $warning['code'] ?? 'WARNING' }}</strong>
                        @if(!empty($warning['field'])) · {{ $warning['field'] }} @endif
                        @if(!empty($warning['message'])) — {{ $warning['message'] }} @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">Raw Source Payload</h3></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach((array) $row->raw_payload as $key => $value)
                    <div class="col-sm-6 col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-secondary small">{{ $key }}</div>
                            <div class="mt-1 text-break">{{ $value === null || $value === '' ? '—' : $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
</div>
@endsection
