@extends('layouts.app')

@section('title', 'Preliminary Result — Category Wise')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Preliminary Result — GG / TT / GT</h2>
                <div class="text-secondary">Passed registration numbers, separately by cadre category, each in ascending registration-number order.</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a class="btn btn-outline-primary" href="{{ route('preliminary.final-result.combined') }}">Combined</a>
                <a class="btn btn-outline-secondary" href="{{ route('preliminary.final-result.category.txt') }}">Download TXT</a>
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

                @foreach (['GG', 'TT', 'GT'] as $category)
                    <section class="mb-5">
                        <h3 class="border-bottom pb-2">{{ $category }}</h3>
                        <div class="result-reg-grid">
                            @foreach ($groups[$category] as $reg)
                                <div class="result-reg">{{ $reg }}</div>
                            @endforeach
                        </div>
                        <div class="mt-3 fw-bold fs-3">TOTAL ({{ $category }}) = {{ number_format($groups[$category]->count()) }}</div>
                    </section>
                @endforeach
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
