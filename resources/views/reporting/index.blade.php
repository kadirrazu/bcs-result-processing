@extends('layouts.app')
@section('title', 'Reporting')

@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col">
        <div class="page-pretitle">{{ $examination->name }}</div>
        <h2 class="page-title">Reporting</h2>
    </div>
</div>
@endsection

@section('content')
@if(!($gate['ready'] ?? false))
<div class="alert alert-warning"><strong>Reporting is currently disabled.</strong> {{ $gate['reason'] ?? 'Complete and finalize Allocation before reporting.' }}</div>
@endif

<div class="row row-cards">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="subheader">Allocation Reporting</div>
                <h3 class="card-title mt-2">A6 - Allocation Reporting &amp; Export</h3>
                <p class="text-secondary">Existing finalized Allocation reporting, candidate/cadre drill-down and TXT/XLSX/DOCX/DBF/PDF export capabilities.</p>
            </div>
            <div class="card-footer"><a class="btn btn-primary w-100 {{ ($gate['ready'] ?? false) ? '' : 'disabled' }}" href="{{ ($gate['ready'] ?? false) ? route('allocation.a6.index') : '#' }}">Open A6 - Allocation Reporting &amp; Export</a></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body"><div class="subheader">Operational Reports</div><h3 class="card-title mt-2">Cadre Section Reporting</h3><p class="text-secondary">Allocation Verification Reports and Booklet Printing Reports for Cadre Section workflows.</p></div>
            <div class="card-footer"><a class="btn btn-primary w-100 {{ ($gate['ready'] ?? false) ? '' : 'disabled' }}" href="{{ ($gate['ready'] ?? false) ? route('examination-reports.cadre.index') : '#' }}">Open Cadre Section Reporting</a></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body"><div class="subheader">Analysis &amp; Research</div><h3 class="card-title mt-2">Research &amp; Statistics Section Reporting</h3><p class="text-secondary">Research, statistical and age-based reporting will use authoritative finalized data and configured examination metadata.</p></div>
            <div class="card-footer"><button class="btn btn-outline-secondary w-100" disabled>Reports will be added next</button></div>
        </div>
    </div>
</div>
@endsection
