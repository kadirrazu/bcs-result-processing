@extends('layouts.app')
@section('title', 'Cadre Section Reporting')
@section('page-header')
<div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Reporting</div><h2 class="page-title">Cadre Section Reporting</h2></div><div class="col-auto ms-auto"><a class="btn btn-outline-secondary" href="{{ route('examination-reports.index') }}">Back to Reporting</a></div></div>
@endsection
@section('content')
@if(!($gate['ready'] ?? false))<div class="alert alert-warning"><strong>Cadre Section Reporting is disabled.</strong> {{ $gate['reason'] ?? '' }}</div>@endif
<div class="row row-cards">
<div class="col-lg-6"><div class="card h-100"><div class="card-body"><div class="subheader">Pre-publication / Identity-free</div><h3 class="card-title mt-2">Allocation Verification Reports</h3><p class="text-secondary">Manual verification of finalized Allocation against Merit and Allocation-ready Choices. Candidate identity fields are intentionally excluded.</p></div><div class="list-group list-group-flush">
@php($ready=(bool)($gate['ready']??false))
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'common']):'#' }}">Common Merit Position Allocation Report</a>
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'general']):'#' }}">General Cadre Candidate Allocation Report</a>
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'technical-only']):'#' }}">Only Technical Cadre Candidate Allocation Report</a>
</div></div></div>
<div class="col-lg-6"><div class="card h-100"><div class="card-body"><div class="subheader">Publication / Identity-bearing</div><h3 class="card-title mt-2">Booklet Printing Reports</h3><p class="text-secondary">Booklet reports will carry approved identity fields in addition to verification evidence.</p></div><div class="card-footer"><button class="btn btn-outline-secondary w-100" disabled>Booklet reports — next subsection</button></div></div></div>
</div>
<div class="card mt-3"><div class="card-header"><div><h3 class="card-title">Technical Cadre-wise Allocation Verification Reports</h3><div class="text-secondary">Every report includes all candidates in the finalized cadre-specific merit-eligible population.</div></div></div><div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Cadre</th><th class="text-center">Allocation Eligible</th><th class="text-end">Report</th></tr></thead><tbody>@forelse($technicalCadres as $cadre)<tr><td><strong>{{ $cadre['abbr'] }}</strong> <span class="text-secondary">({{ $cadre['code'] }})</span></td><td class="text-center">{{ number_format($cadre['eligible_count']) }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification.technical',['cadreCode'=>$cadre['code']]):'#' }}">Open Verification Report</a></td></tr>@empty<tr><td colspan="3" class="text-center text-secondary py-4">No current technical cadre merit evidence found.</td></tr>@endforelse</tbody></table></div></div>
@endsection
