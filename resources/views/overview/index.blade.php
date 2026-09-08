@extends('layouts.app')
@section('title', 'Processing Overview')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">Processing Workspace</div>
        <h2 class="page-title">{{ $examination->name }} — Overview</h2>
    </div>
</div>
@endsection

@section('content')
<div class="row row-cards mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="subheader">BCS Type</div><div class="h3 mb-0">{{ $examination->bcs_type?->label() ?? 'Not specified' }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="subheader">Advertisement Date</div><div class="h3 mb-0">{{ $examination->advertisement_date?->format('d M Y') ?? 'Not set' }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="subheader">Age Calculation Date</div><div class="h3 mb-0">{{ $examination->age_calculation_date?->format('d M Y') ?? 'Not set' }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="subheader">Examination State</div><div class="h3 mb-0 {{ $examination->is_completed ? 'text-success' : 'text-primary' }}">{{ $examination->is_completed ? 'Completed' : 'Processing Open' }}</div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><div><h3 class="card-title">Current Processing Health</h3><div class="card-subtitle">Lightweight status from authoritative module state/summary records.</div></div></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><div class="subheader">Reporting Readiness</div><div class="h3 mb-0 {{ ($reporting['ready'] ?? false) ? 'text-success' : 'text-danger' }}">{{ ($reporting['ready'] ?? false) ? 'READY' : 'BLOCKED' }}</div></div>
            <div class="col-md-4"><div class="subheader">Stale Modules</div><div class="h3 mb-0">{{ $stale_count }}</div></div>
            <div class="col-md-4"><div class="subheader">Not Started</div><div class="h3 mb-0">{{ $not_started_count }}</div></div>
        </div>
        @unless($reporting['ready'] ?? false)<div class="alert alert-warning mt-3 mb-0">{{ $reporting['reason'] ?? 'Allocation reporting authority is not ready.' }}</div>@endunless
    </div>
</div>

<div class="card">
    <div class="card-header"><div><h3 class="card-title">Module Status &amp; Key Statistics</h3><div class="card-subtitle">Open any module for detailed operational data.</div></div></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Module</th><th>Status</th><th>Key Statistics</th><th class="w-1"></th></tr></thead>
            <tbody>
            @foreach($modules as $module)
                <tr>
                    <td class="fw-semibold">{{ $module['name'] }}</td>
                    <td><span class="badge bg-{{ $module['tone'] }}-lt">{{ $module['status'] }}</span></td>
                    <td>
                        @if($module['stats'])
                            <div class="d-flex flex-wrap gap-2">
                            @foreach($module['stats'] as $label => $value)
                                <span class="text-nowrap"><span class="text-secondary">{{ $label }}:</span> <strong>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</strong></span>
                            @endforeach
                            </div>
                        @else
                            <span class="text-secondary">No summary available yet.</span>
                        @endif
                    </td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route($module['route']) }}">Open</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
