@extends('layouts.app')
@section('title','Choice Validation Finalization')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <h2 class="page-title">Choice Validation Finalization</h2>
        <div class="text-secondary">Freeze the reviewed Choice Validation dataset for downstream consumption.</div>
    </div>
    <div class="col-auto ms-auto">
        <a href="{{ route('choice-validation.index') }}" class="btn btn-outline-secondary">Back to Choice Validation</a>
    </div>
</div>
@endsection

@section('content')
@if($errors->any())<div class="alert alert-danger"><strong>Finalization blocked.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="row row-cards mb-3 align-items-stretch">
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Source Version</div><div class="h2">{{ $readiness['state']->approved_source_version ?: '—' }}</div><div class="small text-secondary">{{ number_format($readiness['source_count']) }} approved source rows</div></div></div></div>
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Validation Version</div><div class="h2">{{ $readiness['state']->current_validation_version ?: '—' }}</div><div class="small text-secondary">{{ number_format($readiness['result_count']) }} processed rows</div></div></div></div>
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Circular Version</div><div class="h2">{{ $readiness['current_circular_version'] ?: '—' }}</div><div class="small text-secondary">Finalized eligibility authority</div></div></div></div>
    <div class="col-sm-6 col-lg-3 d-flex"><div class="card card-sm h-100 w-100"><div class="card-body"><div class="text-secondary">Finalization Readiness</div><div class="h3 mb-1">{{ $readiness['ready'] ? 'READY' : 'BLOCKED' }}</div><div class="small text-secondary">{{ $readiness['pending_manual_corrections'] }} pending manual revalidation</div></div></div></div>
</div>

@if(!$readiness['ready'])
<div class="alert alert-warning">
    <strong>Resolve these items before finalization:</strong>
    <ul class="mb-0 mt-2">
        @foreach($readiness['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach
    </ul>
</div>
@else
<div class="card mb-3 border-success">
    <div class="card-header"><h3 class="card-title">Finalize Current Choice Validation</h3></div>
    <form method="POST" action="{{ route('choice-validation.finalization.finalize') }}">
        @csrf
        <div class="card-body">
            <div class="alert alert-info">
                Finalization binds the exact current Choice Validation dataset to a SHA-256 dataset hash.
                Any later material source/manual correction will require revalidation and re-finalization.
            </div>
            <label class="form-label required">Finalization Note</label>
            <textarea name="finalization_note" rows="3" class="form-control @error('finalization_note') is-invalid @enderror" required>{{ old('finalization_note') }}</textarea>
            @error('finalization_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="card-footer text-end"><button class="btn btn-success">Finalize Choice Validation</button></div>
    </form>
</div>
@endif

<div class="card">
    <div class="card-header"><h3 class="card-title">Finalization History</h3></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>#</th><th>Validation v</th><th>Source v</th><th>Circular v</th><th>Hash</th><th>Finalized By</th><th>Finalized At</th><th>Note</th></tr></thead>
            <tbody>
            @forelse($history as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->validation_version }}</td>
                    <td>{{ $row->source_version }}</td>
                    <td>{{ $row->circular_version }}</td>
                    <td><code title="{{ $row->dataset_hash }}">{{ substr($row->dataset_hash,0,12) }}…</code></td>
                    <td>{{ $row->finalized_by_name ?: $row->finalized_by }}</td>
                    <td>{{ optional($row->finalized_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $row->finalization_note }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">No Choice Validation finalization history yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
