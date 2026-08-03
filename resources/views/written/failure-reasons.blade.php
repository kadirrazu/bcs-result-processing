@extends('layouts.app')
@section('title','Written Failure Reasons')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
    <div class="col"><h2 class="page-title">Written Track Failure Review</h2><div class="text-secondary">Shows candidates who failed at least one applicable Written track, with the recorded reason in plain language.</div></div>
    <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('written.index') }}">Back to Written</a></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
<div class="card mb-3"><div class="card-body"><form class="row g-2 align-items-end" method="get">
    <div class="col-md-4"><label class="form-label">Registration / User ID</label><input class="form-control" name="search" value="{{ $search }}"></div>
    <div class="col-md-3"><label class="form-label">Show</label><select class="form-select" name="scope"><option value="all" @selected($scope==='all')>All track failures</option><option value="fully_failed" @selected($scope==='fully_failed')>Candidates with no qualifying track</option><option value="partially_qualified" @selected($scope==='partially_qualified')>GN / T candidates</option></select></div>
    <div class="col-md-auto"><button class="btn btn-primary">Apply filter</button></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>REG</th><th>USER</th><th>Category</th><th>Qualified Track</th><th>General</th><th>Technical</th><th>Reason</th><th></th></tr></thead><tbody>
@forelse($rows as $row)
@php
    $generalReasons = \App\Support\WrittenResultPresenter::reasons($row->general_fail_reasons);
    $technicalReasons = \App\Support\WrittenResultPresenter::reasons($row->technical_fail_reasons);
@endphp
<tr>
    <td class="fw-medium">{{ $row->reg }}</td><td>{{ $row->user_id }}</td><td>{{ $row->cadre_category === 1 ? '1 - GG' : ($row->cadre_category === 2 ? '2 - TT' : '3 - GT') }}</td>
    <td>{{ $row->written_qualified_track?->value ?? '—' }}</td>
    <td>{{ ucfirst($row->general_result_status?->value ?? '—') }}</td><td>{{ ucfirst($row->technical_result_status?->value ?? '—') }}</td>
    <td class="text-wrap" style="min-width:360px">
        @if($generalReasons)<div><strong>General:</strong> {{ implode(' ', $generalReasons) }}</div>@endif
        @if($technicalReasons)<div><strong>Technical:</strong> {{ implode(' ', $technicalReasons) }}</div>@endif
    </td>
    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('written.results.show',$row) }}">View</a></td>
</tr>
@empty<tr><td colspan="8" class="text-center text-secondary py-4">No candidates match this review filter.</td></tr>@endforelse
</tbody></table></div><div class="card-footer d-flex align-items-center justify-content-between gap-3 flex-wrap"><x-pagination-summary :paginator="$rows" />@if($rows->hasPages()){{ $rows->links() }}@endif</div></div>
</div></div>
@endsection
