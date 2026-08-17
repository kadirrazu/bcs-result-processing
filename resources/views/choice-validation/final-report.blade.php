@extends('layouts.app')
@section('title','Final Choice Validation Report')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <h2 class="page-title">Final Choice Validation Report</h2>
        <div class="text-secondary">Read-only finalized Choice Validation dataset.</div>
    </div>
    <div class="col-auto ms-auto d-flex gap-2">
        <a class="btn btn-outline-danger" href="{{ route('choice-validation.final-report.pdf') }}">Export PDF Summary</a>
        <a class="btn btn-outline-success" href="{{ route('choice-validation.final-report.excel') }}">Export Excel</a>
        <a class="btn btn-outline-secondary" href="{{ route('choice-validation.index') }}">Back</a>
    </div>
</div>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2"><div class="text-secondary small">Status</div><div class="h3 text-success">FINALIZED</div></div>
            <div class="col-md-2"><div class="text-secondary small">Validation Version</div><div class="h3">{{ $summary['validation_version'] }}</div></div>
            <div class="col-md-2"><div class="text-secondary small">Source Version</div><div class="h3">{{ $summary['source_version'] }}</div></div>
            <div class="col-md-2"><div class="text-secondary small">Circular Version</div><div class="h3">{{ $summary['circular_version'] }}</div></div>
            <div class="col-md-4"><div class="text-secondary small">Dataset Hash</div><code title="{{ $summary['dataset_hash'] }}">{{ substr($summary['dataset_hash'],0,24) }}…</code></div>
            <div class="col-md-4"><div class="text-secondary small">Finalized By</div><div>{{ $summary['finalized_by_name'] ?: '—' }}</div></div>
            <div class="col-md-4"><div class="text-secondary small">Finalized At</div><div>{{ optional($summary['finalized_at'])->format('Y-m-d H:i:s') }}</div></div>
            <div class="col-md-4"><div class="text-secondary small">Finalization Note</div><div>{{ $summary['finalization_note'] }}</div></div>
        </div>
    </div>
</div>

<div class="row row-cards mb-3 align-items-stretch">
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Total Candidates</div><div class="h2">{{ number_format($summary['total_candidates']) }}</div></div></div></div>
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Valid</div><div class="h2">{{ number_format($summary['valid_candidates']) }}</div></div></div></div>
    <div class="col-sm-6 col-lg-3 d-flex">
        <div class="card card-sm h-100 w-100">
            <div class="card-body">
                <div class="text-secondary">Not Applicable</div>
                <div class="h2">{{ number_format($summary['not_applicable_candidates']) }}</div>
                @foreach($notApplicableBreakdown as $naStatus => $count)
                    <div class="small text-secondary">{{ \App\Support\ChoiceValidationStatusPresenter::resultLabel($naStatus) }}: <strong>{{ number_format($count) }}</strong></div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">No Valid Choice</div><div class="h2">{{ number_format($summary['zero_valid_choice_candidates']) }}</div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input class="form-control" name="search" value="{{ $search }}" placeholder="Reg or User"></div>
            <div class="col-md-5">
                <select name="status" class="form-select">
                    <option value="all">All status</option>
                    @foreach($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}" @selected($status===$statusOption)>{{ \App\Support\ChoiceValidationStatusPresenter::resultLabel($statusOption) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Candidate</th><th>Original Category</th><th>Written Category</th><th>Current Track</th><th>Status</th><th>Validated Choices</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $cat = $row->registration?->cadre_category;
                    $catCode = is_object($cat) && method_exists($cat,'code') ? $cat->code() : (string)($cat ?? '—');
                @endphp
                <tr>
                    <td><div class="fw-semibold">{{ $row->registration?->name ?: '—' }}</div><div class="small">Reg: {{ $row->reg }}</div><div class="small text-secondary">User: {{ $row->user_id }}</div></td>
                    <td>{{ $catCode }}</td>
                    <td>{{ $row->written_qualified_track ?: '—' }}</td>
                    <td>{{ strtoupper($row->effective_track ?: '—') }}</td>
                    <td><span class="badge {{ \App\Support\ChoiceValidationStatusPresenter::resultBadgeClass($row->status) }}">{{ \App\Support\ChoiceValidationStatusPresenter::resultLabel($row->status) }}</span></td>
                    <td><code>{{ implode(' ',(array)$row->validated_choice_codes) }}</code></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">No finalized Choice Validation rows.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection
