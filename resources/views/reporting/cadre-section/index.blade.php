@extends('layouts.app')
@section('title', 'Cadre Section Reporting')
@section('page-header')
<div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Reporting</div><h2 class="page-title">Cadre Section Reporting</h2></div><div class="col-auto ms-auto"><a class="btn btn-outline-secondary" href="{{ route('examination-reports.index') }}">Back to Reporting</a></div></div>
@endsection
@section('content')
@if(!($gate['ready'] ?? false))
<div class="alert alert-warning"><strong>Cadre Section Reporting is disabled.</strong> {{ $gate['reason'] ?? '' }}</div>
@endif
@php($ready=(bool)($gate['ready']??false))

<div class="card">
<div class="card-header">
<div>
<div class="subheader">Pre-publication / Identity-free</div>
<h3 class="card-title mt-2 mb-0">Allocation Verification Reports</h3>
<div class="text-secondary mt-1">Manual verification of finalized Allocation against Merit and Allocation-ready Choices. Candidate identity fields are intentionally excluded.</div>
</div>
</div>
<div class="list-group list-group-flush">
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'common']):'#' }}"><strong>1.</strong> Common Merit Position Allocation Report</a>
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'general']):'#' }}"><strong>2.</strong> General Cadre Candidate Allocation Report</a>
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'technical-only']):'#' }}"><strong>3.</strong> Only Technical Cadre Candidate Allocation Report</a>
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification.general-cadre-wise'):'#' }}"><strong>4.</strong> General Cadre-wise Allocation Verification Reports</a>
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification.technical-cadre-wise'):'#' }}"><strong>5.</strong> Technical Cadre-wise Allocation Verification Reports</a>
<a class="list-group-item list-group-item-action {{ $ready?'':'disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification.cadre-serial-merit'):'#' }}"><strong>6.</strong> Cadre-wise Serial, Merit &amp; Allocation Basis Report</a>
</div>
</div>

<div class="card mt-3">
<div class="card-body">
<div class="subheader">Publication / Identity-bearing</div>
<h3 class="card-title mt-2">Booklet Printing Reports</h3>
<p class="text-secondary mb-0">Booklet reports will carry approved identity fields in addition to verification evidence.</p>
</div>
<div class="card-footer"><button class="btn btn-outline-secondary w-100" disabled>Booklet reports — next subsection</button></div>
</div>
@endsection
