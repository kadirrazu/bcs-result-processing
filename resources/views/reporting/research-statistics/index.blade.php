@extends('layouts.app')
@section('title', 'Research & Statistics')
@section('page-header')
<div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">{{ $examination->name }}</div><h2 class="page-title">Research &amp; Statistics Section Reporting</h2></div><div class="col-auto ms-auto"><a href="{{ route('examination-reports.index') }}" class="btn btn-outline-secondary">Back to Reporting</a></div></div>
@endsection
@section('content')
<style>
.rs-category{border:1px solid var(--tblr-border-color);border-radius:14px;overflow:hidden;background:var(--tblr-bg-surface);box-shadow:0 1px 2px rgba(0,0,0,.03)}
.rs-category-head{padding:18px 20px;border-bottom:1px solid var(--tblr-border-color);display:flex;align-items:center;gap:12px}.rs-list{padding:8px}.rs-link{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:10px;color:inherit;text-decoration:none}.rs-link:hover{background:var(--tblr-bg-surface-secondary);text-decoration:none}.rs-number{width:30px;height:30px;border-radius:8px;background:var(--tblr-bg-surface-secondary);display:grid;place-items:center;font-size:12px;font-weight:700}.rs-arrow{margin-left:auto;font-size:22px;color:var(--tblr-secondary)}
</style>
<div class="row g-3">
@foreach($catalog as $key => $category)
<div class="col-12"><section class="rs-category"><div class="rs-category-head"><div><div class="subheader">Research &amp; Statistics</div><h3 class="card-title mt-1">{{ $category['title'] }}</h3></div><span class="badge ms-auto {{ $availability[$key]['ready'] ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">{{ $availability[$key]['ready'] ? 'CURRENT / READY' : 'NOT READY' }}</span></div>
@if($availability[$key]['ready'])<div class="rs-list">@foreach($category['reports'] as $reportKey => $title)<a class="rs-link" href="{{ route('examination-reports.research-statistics.show', $reportKey) }}"><span class="rs-number">{{ str_pad((string)$loop->iteration,2,'0',STR_PAD_LEFT) }}</span><span class="fw-semibold">{{ $title }}</span><span class="rs-arrow">›</span></a>@endforeach</div>@else<div class="p-3 text-secondary">{{ $availability[$key]['reason'] }}</div>@endif
</section></div>
@endforeach
</div>
@endsection
