@extends('layouts.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Choice Optimization</h2>
                <div class="text-secondary">Optional transformation layer between finalized Choice Validation and Allocation.</div>
            </div>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container-xl">
        <div class="row row-cards mb-3">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Optimization Setting</h3></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge {{ $setting->optimization_enabled ? 'bg-green-lt' : 'bg-secondary-lt' }}">
                                {{ $setting->optimization_enabled ? 'YES — ENABLED' : 'NO — BYPASS' }}
                            </span>
                        </div>
                        <p class="text-secondary">
                            YES: Viva OMR override and previous-BCS optimization must be completed before Allocation.<br>
                            NO: Allocation consumes finalized Validated Choice directly; this module performs no transformation.
                        </p>
                        <form method="POST" action="{{ route('choice-optimization.setting.update') }}" class="d-flex gap-2">
                            @csrf
                            <button class="btn {{ $setting->optimization_enabled ? 'btn-primary' : 'btn-outline-primary' }}" name="optimization_enabled" value="1" type="submit">YES</button>
                            <button class="btn {{ ! $setting->optimization_enabled ? 'btn-secondary' : 'btn-outline-secondary' }}" name="optimization_enabled" value="0" type="submit">NO</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Processing Status Board</h3></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="text-secondary small">Current state</div><div class="fw-semibold">{{ strtoupper(str_replace('_', ' ', $state->status)) }}</div></div></div>
                            <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="text-secondary small">Allocation choice source</div><div class="fw-semibold">{{ $setting->optimization_enabled ? 'Finalized Optimized Choice' : 'Finalized Validated Choice' }}</div></div></div>
                            <div class="col-12"><div class="alert alert-info mb-0">CO2 adds Viva OMR raw staging plus Written-qualified registration/decision validation. OMR choice-code revalidation and override application remain the next stage.</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($setting->optimization_enabled)
        <div class="row row-cards">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Viva OMR Choice Source</h3></div>
                    <div class="card-body">
                        <div class="mb-3 text-secondary">Columns: <code>reg</code>, <code>change_choice</code>, <code>opt_01 ... opt_{{ str_pad((string) config('choice-optimization.omr_max_choices', 20), 2, '0', STR_PAD_LEFT) }}</code>.</div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <a class="btn btn-outline-secondary" href="{{ route('choice-optimization.omr.template') }}">Download Template</a>
                        </div>
                        <form method="POST" action="{{ route('choice-optimization.omr.upload') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="input-group">
                                <input class="form-control" type="file" name="file" accept=".xlsx,.csv" required>
                                <button class="btn btn-primary" type="submit">Upload & Stage</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Latest OMR Batch</h3></div>
                    <div class="card-body">
                        @if($latestOmrBatch)
                            <div class="row g-3">
                                <div class="col-md-4"><div class="text-secondary small">Status</div><div class="fw-semibold">{{ strtoupper(str_replace('_',' ', $latestOmrBatch->status)) }}</div></div>
                                <div class="col-md-4"><div class="text-secondary small">Rows</div><div class="fw-semibold">{{ number_format($latestOmrBatch->total_rows) }}</div></div>
                                <div class="col-md-4"><div class="text-secondary small">Conflict</div><div class="fw-semibold">{{ number_format($latestOmrBatch->conflict_rows) }}</div></div>
                            </div>
                            <div class="mt-3"><a class="btn btn-outline-primary" href="{{ route('choice-optimization.omr.show', $latestOmrBatch) }}">Review Batch</a></div>
                        @else
                            <div class="text-secondary">No Viva OMR source has been uploaded yet.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
