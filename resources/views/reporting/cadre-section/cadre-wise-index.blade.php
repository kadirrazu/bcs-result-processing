@extends('layouts.app')
@section('title', $title)
@section('page-header')
<div class="row g-2 align-items-center">
<div class="col">
<div class="page-pretitle">{{ $examination->name }} · Allocation Verification Reports</div>
<h2 class="page-title">{{ $title }}</h2>
</div>
<div class="col-auto ms-auto">
<a class="btn btn-outline-secondary" href="{{ route('examination-reports.cadre.index') }}">Back to Cadre Section Reporting</a>
</div>
</div>
@endsection

@section('content')
<div class="card">
<div class="card-header">
<div>
<h3 class="card-title">{{ $title }}</h3>
<div class="text-secondary">{{ $description }}</div>
</div>
</div>

<div class="card-body border-bottom">
<div class="row g-2">
<div class="col-md-6">
<label class="form-label">Search by Cadre Code or Abbreviation</label>
<input id="cadre-search" type="search" class="form-control" placeholder="{{ $searchPlaceholder }}">
</div>
<div class="col-md-6">
<label class="form-label">Filter Cadre</label>
<select id="cadre-filter" class="form-select">
<option value="">{{ $filterLabel }}</option>
@foreach($cadres as $cadre)
<option value="{{ $cadre['code'] }}|{{ strtoupper($cadre['abbr']) }}">{{ $cadre['code'] }} - {{ $cadre['abbr'] }}</option>
@endforeach
</select>
</div>
</div>
<div id="cadre-filter-status" class="small text-secondary mt-2"></div>
</div>

<div class="table-responsive">
<table class="table table-vcenter mb-0">
<thead>
<tr>
<th>Cadre</th>
<th class="text-center">Allocation Eligible</th>
<th class="text-end">Report</th>
</tr>
</thead>
<tbody>
@forelse($cadres as $cadre)
<tr class="cadre-report-row" data-code="{{ $cadre['code'] }}" data-abbr="{{ strtoupper($cadre['abbr']) }}">
<td><strong>{{ $cadre['abbr'] }}</strong> <span class="text-secondary">({{ $cadre['code'] }})</span></td>
<td class="text-center">{{ number_format($cadre['eligible_count']) }}</td>
<td class="text-end">
@if((int)$cadre['eligible_count'] > 0)
@if($kind === 'general')
<a class="btn btn-sm btn-outline-primary" href="{{ route('examination-reports.cadre.verification.general-cadre',['cadreCode'=>$cadre['code']]) }}">Open Verification Report</a>
@else
<a class="btn btn-sm btn-outline-primary" href="{{ route('examination-reports.cadre.verification.technical',['cadreCode'=>$cadre['code']]) }}">Open Verification Report</a>
@endif
@else
<span class="text-secondary">—</span>
@endif
</td>
</tr>
@empty
<tr><td colspan="3" class="text-center text-secondary py-4">{{ $emptyMessage }}</td></tr>
@endforelse
<tr id="cadre-no-match" class="d-none"><td colspan="3" class="text-center text-secondary py-4">No cadre matches the current search/filter.</td></tr>
</tbody>
</table>
</div>
</div>

<script>
(()=>{
    const search=document.getElementById('cadre-search');
    const filter=document.getElementById('cadre-filter');
    const status=document.getElementById('cadre-filter-status');
    const rows=[...document.querySelectorAll('.cadre-report-row')];
    const none=document.getElementById('cadre-no-match');
    if(!search||!filter)return;

    const apply=()=>{
        const q=search.value.trim().toUpperCase();
        const selected=filter.value;
        let visible=0;

        rows.forEach(row=>{
            const code=String(row.dataset.code||'');
            const abbr=String(row.dataset.abbr||'').toUpperCase();
            const show=(q===''||code.includes(q)||abbr.includes(q))
                &&(selected===''||(code+'|'+abbr)===selected);
            row.classList.toggle('d-none',!show);
            if(show)visible++;
        });

        if(none)none.classList.toggle('d-none',visible!==0);
        if(status)status.textContent=visible.toLocaleString()+' cadre(s) shown';
    };

    search.addEventListener('input',apply);
    filter.addEventListener('change',apply);
    apply();
})();
</script>
@endsection
