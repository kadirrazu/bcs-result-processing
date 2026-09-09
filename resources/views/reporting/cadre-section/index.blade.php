@extends('layouts.app')
@section('title', 'Cadre Section Reporting')
@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">Reporting</div>
        <h2 class="page-title">Cadre Section Reporting</h2>
    </div>
    <div class="col-auto ms-auto">
        <a class="btn btn-outline-secondary" href="{{ route('examination-reports.index') }}">Back to Reporting</a>
    </div>
</div>
@endsection

@section('content')
@php($ready=(bool)($gate['ready']??false))
<style>
.csr-wrap{max-width:1080px;margin:0 auto}
.csr-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}
.csr-section-title{font-size:1.1rem;font-weight:700;margin:0}
.csr-section-copy{font-size:.875rem;color:var(--tblr-secondary-color);max-width:700px;margin-top:.25rem}
.csr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}
.csr-tile{display:flex;align-items:center;gap:.9rem;min-height:88px;padding:1rem 1.05rem;border:1px solid var(--tblr-border-color);border-radius:12px;background:var(--tblr-bg-surface);color:inherit;text-decoration:none;transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}
.csr-tile:hover{color:inherit;text-decoration:none;border-color:rgba(var(--tblr-primary-rgb),.45);box-shadow:0 8px 24px rgba(24,36,51,.08);transform:translateY(-1px)}
.csr-tile.is-disabled{pointer-events:none;opacity:.55}
.csr-number{display:flex;align-items:center;justify-content:center;flex:0 0 38px;width:38px;height:38px;border-radius:10px;background:rgba(var(--tblr-primary-rgb),.09);color:var(--tblr-primary);font-weight:700;font-size:.82rem}
.csr-copy{min-width:0;flex:1}
.csr-name{font-weight:650;line-height:1.25}
.csr-desc{margin-top:.25rem;font-size:.78rem;color:var(--tblr-secondary-color);line-height:1.3}
.csr-arrow{font-size:1.25rem;color:var(--tblr-secondary-color);line-height:1}
.csr-booklet{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border:1px solid var(--tblr-border-color);border-radius:12px;background:var(--tblr-bg-surface)}
@media(max-width:767.98px){.csr-grid{grid-template-columns:1fr}.csr-section-head{align-items:flex-start;flex-direction:column}.csr-booklet{align-items:flex-start;flex-direction:column}}
</style>

<div class="csr-wrap">
    @if(!$ready)
        <div class="alert alert-warning">
            <strong>Cadre Section Reporting is disabled.</strong> {{ $gate['reason'] ?? '' }}
        </div>
    @endif

    <div class="csr-section-head">
        <div>
            <div class="subheader">Pre-publication / Identity-free</div>
            <h3 class="csr-section-title">Allocation Verification Reports</h3>
            <div class="csr-section-copy">Compact verification views of finalized Allocation, Merit, quota and Allocation-ready Choice evidence. Candidate identity is intentionally excluded.</div>
        </div>
    </div>

    <div class="csr-grid">
        <a class="csr-tile {{ $ready?'':'is-disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'common']):'#' }}">
            <span class="csr-number">01</span><span class="csr-copy"><span class="csr-name">Common Merit Position Allocation Report</span><span class="csr-desc d-block">All applicable candidates in Common Merit order.</span></span><span class="csr-arrow">›</span>
        </a>
        <a class="csr-tile {{ $ready?'':'is-disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'general']):'#' }}">
            <span class="csr-number">02</span><span class="csr-copy"><span class="csr-name">General Cadre Candidate Allocation Report</span><span class="csr-desc d-block">General-side finalized merit population and allocation evidence.</span></span><span class="csr-arrow">›</span>
        </a>
        <a class="csr-tile {{ $ready?'':'is-disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'technical-only']):'#' }}">
            <span class="csr-number">03</span><span class="csr-copy"><span class="csr-name">Only Technical Cadre Candidate Allocation Report</span><span class="csr-desc d-block">Effective TT + T technical-only population.</span></span><span class="csr-arrow">›</span>
        </a>
        <a class="csr-tile {{ $ready?'':'is-disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification',['type'=>'quota']):'#' }}">
            <span class="csr-number">04</span><span class="csr-copy"><span class="csr-name">Quota Candidate Allocation Verification Report</span><span class="csr-desc d-block">CFF, EM and PHC candidates with allocation outcome and missed-choice evidence.</span></span><span class="csr-arrow">›</span>
        </a>
        <a class="csr-tile {{ $ready?'':'is-disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification.general-cadre-wise'):'#' }}">
            <span class="csr-number">05</span><span class="csr-copy"><span class="csr-name">General Cadre-wise Allocation Verification Reports</span><span class="csr-desc d-block">Search and open a verification report for a General cadre.</span></span><span class="csr-arrow">›</span>
        </a>
        <a class="csr-tile {{ $ready?'':'is-disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification.technical-cadre-wise'):'#' }}">
            <span class="csr-number">06</span><span class="csr-copy"><span class="csr-name">Technical Cadre-wise Allocation Verification Reports</span><span class="csr-desc d-block">Search and open a cadre-specific Technical merit report.</span></span><span class="csr-arrow">›</span>
        </a>
        <a class="csr-tile {{ $ready?'':'is-disabled' }}" href="{{ $ready?route('examination-reports.cadre.verification.cadre-serial-merit'):'#' }}">
            <span class="csr-number">07</span><span class="csr-copy"><span class="csr-name">Cadre-wise Serial, Merit &amp; Allocation Basis Report</span><span class="csr-desc d-block">Compact publication-order serial, merit and allocation basis verification.</span></span><span class="csr-arrow">›</span>
        </a>
    </div>

    <div class="mt-4 mb-2">
        <div class="subheader">Publication / Identity-bearing</div>
    </div>
    <div class="csr-booklet">
        <div>
            <div class="fw-semibold">Booklet Printing Reports</div>
            <div class="small text-secondary mt-1">Approved identity-bearing booklet outputs will be managed here.</div>
        </div>
        <span class="badge bg-secondary-lt">Next subsection</span>
    </div>
</div>
@endsection
