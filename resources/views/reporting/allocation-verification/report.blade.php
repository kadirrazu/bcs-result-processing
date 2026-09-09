@extends('layouts.app')
@section('title', $title)

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">{{ $examination->name }}</div>
        <h2 class="page-title">{{ $title }}</h2>
        @if($cadre)
            <div class="text-secondary">Cadre Name: {{ $cadre['abbr'] }} — {{ $cadre['name'] }} ({{ $cadre['code'] }})</div>
        @endif
    </div>
    <div class="col-auto ms-auto d-flex gap-2">
        @if($reportType === 'technical-cadre')
            <form method="POST" action="{{ route('examination-reports.cadre.verification.technical.pdf',['cadreCode'=>$cadreCode]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
        @elseif($reportType === 'general-cadre')
            <form method="POST" action="{{ route('examination-reports.cadre.verification.general-cadre.pdf',['cadreCode'=>$cadreCode]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
        @else
            <form method="POST" action="{{ route('examination-reports.cadre.verification.pdf',['type'=>$reportType]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
        @endif
        <button class="btn btn-outline-primary" onclick="window.print()">Print</button>
        <a class="btn btn-outline-secondary" href="{{ route('examination-reports.cadre.index') }}">Back</a>
    </div>
</div>
@endsection

@section('content')
@include('reporting.allocation-verification._verification-table-style')

<div class="alert alert-info py-2 no-print">
    <strong>Confidentiality:</strong> Identity-free pre-publication verification report. Registration number, name, date of birth and other candidate identity fields are intentionally excluded.
</div>

<div class="card">
    <div class="card-body border-bottom page-break-avoid">
        <div><strong>Exam Name:</strong> {{ $examination->name }}</div>
        <div><strong>Report Title:</strong> {{ $title }}</div>
        @if($cadre)<div><strong>Cadre Name:</strong> {{ $cadre['abbr'] }} — {{ $cadre['name'] }} ({{ $cadre['code'] }})</div>@endif

        @include('reporting.allocation-verification._technical-cadre-summary', [
            'summary' => $summary,
            'cadre' => $cadre,
            'class' => 'mt-2 avr-summary',
        ])

        @include('reporting.allocation-verification._quota-summary', [
            'quotaSummary' => $quotaSummary ?? null,
            'class' => 'mt-2',
        ])
    </div>

    @if($reportType === 'quota')
        @php
            $allocatedCadres = $rows->pluck('allocation_abbr')->filter()->unique()->sort()->values();
        @endphp
        <div class="card-body border-bottom no-print">
            <div class="avr-filter-card">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label mb-1">Quota</label>
                        <select id="quota-filter" class="form-select form-select-sm">
                            <option value="">All Quota</option>
                            <option value="CFF">CFF</option>
                            <option value="EM">EM</option>
                            <option value="PHC">PHC</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Allocation Status</label>
                        <select id="quota-outcome-filter" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="ALLOCATED">Allocated</option>
                            <option value="NOT_ALLOCATED">Not Allocated</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Allocated Cadre</label>
                        <select id="quota-cadre-filter" class="form-select form-select-sm">
                            <option value="">All Cadres</option>
                            @foreach($allocatedCadres as $allocatedCadre)
                                <option value="{{ $allocatedCadre }}">{{ $allocatedCadre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="quota-filter-status" class="small text-secondary mt-2"></div>
            </div>
        </div>
    @endif

    <div class="table-responsive">
        @include('reporting.allocation-verification._verification-table', [
            'rows' => $rows,
            'reportType' => $reportType,
            'interactive' => true,
        ])
    </div>

    @if($summary)
        <div class="card-body border-top page-break-avoid">
            @include('reporting.allocation-verification._technical-cadre-summary', [
                'summary' => $summary,
                'cadre' => $cadre,
                'class' => 'avr-summary',
            ])
        </div>
    @endif

    @if($quotaSummary ?? null)
        <div class="card-body border-top page-break-avoid">
            @include('reporting.allocation-verification._quota-summary', [
                'quotaSummary' => $quotaSummary,
                'class' => '',
            ])
        </div>
    @endif
</div>

@if($reportType === 'quota')
<script>
(()=>{
    const quota=document.getElementById('quota-filter');
    const outcome=document.getElementById('quota-outcome-filter');
    const cadre=document.getElementById('quota-cadre-filter');
    const status=document.getElementById('quota-filter-status');
    const rows=[...document.querySelectorAll('.quota-report-row')];
    const none=document.getElementById('quota-no-match');
    if(!quota||!outcome||!cadre)return;

    const apply=()=>{
        let visible=0;
        rows.forEach(row=>{
            const quotas=String(row.dataset.quotas||'').split(',').filter(Boolean);
            const showQuota=quota.value===''||quotas.includes(quota.value);
            const showOutcome=outcome.value===''||row.dataset.outcome===outcome.value;
            const showCadre=cadre.value===''||row.dataset.cadre===cadre.value;
            const show=showQuota&&showOutcome&&showCadre;
            row.classList.toggle('d-none',!show);
            if(show)visible++;
        });
        if(none)none.classList.toggle('d-none',visible!==0);
        if(status)status.textContent=visible.toLocaleString()+' quota candidate(s) shown';
    };

    quota.addEventListener('change',apply);
    outcome.addEventListener('change',apply);
    cadre.addEventListener('change',apply);
    apply();
})();
</script>
@endif
@endsection
