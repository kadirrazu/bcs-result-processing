@extends('layouts.app')
@section('title', $report['title'])
@section('page-header')
<div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">{{ $examination->name }} · Research &amp; Statistics</div><h2 class="page-title">{{ $report['title'] }}</h2></div><div class="col-auto ms-auto d-flex gap-2"><a href="{{ route('examination-reports.research-statistics.index') }}" class="btn btn-outline-secondary">Back to Statistics</a><a href="{{ route('examination-reports.research-statistics.pdf', $report['key']) }}" class="btn btn-primary">Export PDF</a></div></div>
@endsection
@section('content')
<div class="card"><div class="card-body border-bottom"><div class="row g-3 align-items-center"><div class="col"><div class="text-secondary">Authoritative current dataset</div><div class="fw-semibold">{{ $report['title'] }}</div>@if(str_contains($report['key'],'-age'))<div class="small text-secondary mt-1">Age calculated on {{ $report['age_date'] }}</div>@endif</div><div class="col-auto"><div class="text-secondary text-end">Total candidates</div><div class="h2 mb-0 text-end">{{ number_format($report['data']['total']['total'] ?? 0) }}</div></div></div></div><div class="card-body p-0">@include('reporting.research-statistics._statistics-table', ['title'=>$report['title'],'data'=>$report['data'],'genderNames'=>$genderNames,'rankedTop10'=>$report['ranked_top_10'],'embedded'=>true])
</div></div>
@endsection
