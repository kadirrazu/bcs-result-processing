@extends('layouts.app')

@section('title', 'Preliminary Result — Combined')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Preliminary Result — All Categories Combined</h2>
                <div class="text-secondary">Passed registration numbers in ascending registration-number order.</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a class="btn btn-outline-primary" href="{{ route('preliminary.final-result.category') }}">GG / TT / GT Separate</a>
                <a class="btn btn-outline-secondary" href="{{ route('preliminary.final-result.combined.txt') }}">Download TXT</a>
                <a class="btn btn-outline-success" href="{{ route('preliminary.exports.xlsx', ['scope'=>'passed','order'=>'reg','direction'=>'asc']) }}">Download XLSX</a>
                <a class="btn btn-outline-success" href="{{ route('preliminary.final-result.template') }}">Fill Result Template</a>
                <button class="btn btn-primary" type="button" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <h2 class="mb-1">Preliminary Examination Result</h2>
                    <div>Cut-off Mark: {{ number_format((float) $state->cutoff_mark, 2) }}</div>
                </div>

                <div class="result-reg-grid">
                    @foreach ($registrations as $reg)
                        <div class="result-reg">{{ $reg }}</div>
                    @endforeach
                </div>

                <div class="mt-4 fw-bold fs-3">TOTAL = {{ number_format($registrations->count()) }}</div>
            </div>
        </div>
    </div>
</div>

<style>
.result-reg-grid { display:grid; grid-template-columns:repeat(8,minmax(0,1fr)); gap:.45rem 1rem; }
.result-reg { font-variant-numeric:tabular-nums; white-space:nowrap; }
@media (max-width: 992px) { .result-reg-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } }
@media print {
    .result-reg-grid { grid-template-columns:repeat(8,minmax(0,1fr)); font-size:11px; }
    .card { border:0 !important; box-shadow:none !important; }
}
</style>
@endsection
