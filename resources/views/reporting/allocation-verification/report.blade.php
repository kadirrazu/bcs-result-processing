@extends('layouts.app')
@section('title', $title)

@php
    $booklet = (bool) ($booklet ?? false);
    $routeBase = $booklet ? 'examination-reports.cadre.booklet' : 'examination-reports.cadre.verification';
    $allocatedCadres = $rows->pluck('allocation_abbr')->filter()->unique()->sort()->values();
@endphp

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
            <form method="POST" action="{{ route($routeBase.'.technical.pdf',['cadreCode'=>$cadreCode]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
        @elseif($reportType === 'general-cadre')
            <form method="POST" action="{{ route($routeBase.'.general-cadre.pdf',['cadreCode'=>$cadreCode]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
        @else
            <form method="POST" action="{{ route($routeBase.'.pdf',['type'=>$reportType]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
        @endif
        <button class="btn btn-outline-primary" onclick="window.print()">Print</button>
        <a class="btn btn-outline-secondary" href="{{ route('examination-reports.cadre.index') }}">Back</a>
    </div>
</div>
@endsection

@section('content')
@include('reporting.allocation-verification._verification-table-style')

@if(!$booklet)
<div class="alert alert-info py-2 no-print">
    <strong>Confidentiality:</strong> Identity-free pre-publication verification report. Registration number, name, date of birth and other candidate identity fields are intentionally excluded.
</div>
@else
<div class="alert alert-success py-2 no-print">
    <strong>Booklet Printing:</strong> This publication-stage report intentionally includes approved candidate identity information.
</div>
@endif

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

    <div class="card-body border-bottom no-print">
        <div class="avr-filter-card">
            <div class="row g-2">
                @if($booklet)
                    <div class="col-md-3">
                        <label class="form-label mb-1">Reg / Name</label>
                        <input id="candidate-filter" type="search" class="form-control form-control-sm" placeholder="Registration or candidate name">
                    </div>
                @endif
                <div class="{{ $booklet ? 'col-md-2' : 'col-md-4' }}">
                    <label class="form-label mb-1">Merit Position</label>
                    <input id="merit-filter" type="search" inputmode="numeric" class="form-control form-control-sm" placeholder="e.g. 125">
                </div>
                <div class="{{ $booklet ? 'col-md-3' : 'col-md-4' }}">
                    <label class="form-label mb-1">Allocated Cadre</label>
                    <select id="{{ $reportType === 'quota' ? 'quota-cadre-filter' : 'cadre-filter' }}" class="form-select form-select-sm">
                        <option value="">All Cadres</option>
                        @foreach($allocatedCadres as $allocatedCadre)
                            <option value="{{ strtoupper($allocatedCadre) }}">{{ $allocatedCadre }}</option>
                        @endforeach
                        <option value="__NONE__">Not Allocated</option>
                    </select>
                </div>
                @if($reportType === 'quota')
                    <div class="{{ $booklet ? 'col-md-2' : 'col-md-2' }}">
                        <label class="form-label mb-1">Quota</label>
                        <select id="quota-filter" class="form-select form-select-sm">
                            <option value="">All Quota</option>
                            <option value="CFF">CFF</option>
                            <option value="EM">EM</option>
                            <option value="PHC">PHC</option>
                        </select>
                    </div>
                    <div class="{{ $booklet ? 'col-md-2' : 'col-md-2' }}">
                        <label class="form-label mb-1">Allocation Status</label>
                        <select id="quota-outcome-filter" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="ALLOCATED">Allocated</option>
                            <option value="NOT_ALLOCATED">Not Allocated</option>
                        </select>
                    </div>
                @endif
            </div>
            <div id="{{ $reportType === 'quota' ? 'quota-filter-status' : 'report-filter-status' }}" class="small text-secondary mt-2"></div>
        </div>
    </div>

    <div class="table-responsive">
        @include('reporting.allocation-verification._verification-table', [
            'rows' => $rows,
            'reportType' => $reportType,
            'interactive' => true,
            'booklet' => $booklet,
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

<script>
(()=>{
    const candidate=document.getElementById('candidate-filter');
    const merit=document.getElementById('merit-filter');
    const cadre=document.getElementById('cadre-filter')||document.getElementById('quota-cadre-filter');
    const quota=document.getElementById('quota-filter');
    const outcome=document.getElementById('quota-outcome-filter');
    const status=document.getElementById('report-filter-status')||document.getElementById('quota-filter-status');
    const rows=[...document.querySelectorAll('.allocation-report-row')];
    const none=document.getElementById('allocation-report-no-match');
    if(!merit||!cadre)return;

    const apply=()=>{
        const candidateQuery=(candidate?.value||'').trim().toUpperCase();
        const meritQuery=merit.value.trim();
        const cadreValue=cadre.value;
        let visible=0;

        rows.forEach(row=>{
            const rowMerit=String(row.dataset.merit||'');
            const rowCadre=String(row.dataset.cadre||'').toUpperCase();
            const identity=(String(row.dataset.reg||'')+' '+String(row.dataset.name||'')).toUpperCase();
            const quotas=String(row.dataset.quotas||'').split(',').filter(Boolean);

            const showCandidate=candidateQuery===''||identity.includes(candidateQuery);
            const showMerit=meritQuery===''||rowMerit===meritQuery;
            const showCadre=cadreValue===''||(cadreValue==='__NONE__'?rowCadre==='':rowCadre===cadreValue);
            const showQuota=!quota||quota.value===''||quotas.includes(quota.value);
            const showOutcome=!outcome||outcome.value===''||row.dataset.outcome===outcome.value;
            const show=showCandidate&&showMerit&&showCadre&&showQuota&&showOutcome;

            row.classList.toggle('d-none',!show);
            if(show)visible++;
        });

        if(none)none.classList.toggle('d-none',visible!==0);
        if(status)status.textContent=visible.toLocaleString()+' candidate(s) shown';
    };

    [candidate,merit,cadre,quota,outcome].filter(Boolean).forEach(control=>{
        control.addEventListener(control.tagName==='SELECT'?'change':'input',apply);
    });
    apply();
})();
</script>
@endsection
