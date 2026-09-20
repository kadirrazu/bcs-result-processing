@extends('layouts.app')
@section('title','Manual Adjustment of Allocation Ready Choice')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><div class="page-pretitle">Choice Optimization — Optional Final Layer</div><h2 class="page-title">Manual Adjustment of Allocation Ready Choice</h2><div class="text-secondary">Optional, non-destructive adjustment. No adjustment means Allocation Ready Choice remains the Final Allocation Ready Choice.</div></div></div></div></div>
<div class="page-body"><div class="container-xl">
<div class="row row-cards mb-3">
<div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Allocation Ready Candidates</div><div class="h2 mb-0">{{ number_format($manualAdjustmentSummary['total_candidates']) }}</div></div></div></div>
<div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Adjusted Candidates</div><div class="h2 mb-0">{{ number_format($manualAdjustmentSummary['adjusted_candidates']) }}</div><div class="small text-secondary mt-1">Current active adjustment</div></div></div></div>
<div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Choices Excluded</div><div class="h2 mb-0">{{ number_format($manualAdjustmentSummary['excluded_choices']) }}</div><div class="small text-secondary mt-1">Currently excluded choices</div></div></div></div>
<div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Unchanged Candidates</div><div class="h2 mb-0">{{ number_format($manualAdjustmentSummary['unchanged_candidates']) }}</div></div></div></div>
</div>
<div class="small text-secondary mb-3">Overview is based on the current effective Final Allocation Ready Choice. Restored choices are excluded from current adjustment counts while full audit history remains available.</div>
<div class="alert alert-info"><strong>Guardrails:</strong> Existing choices may only be excluded or restored with a mandatory audited reason. New choices cannot be added and choices cannot be reordered. Any effective change makes the current Allocation lineage stale, but does not stale Seat Breakup or finalized Choice Optimization.</div>
<div class="card mb-3"><div class="card-body">
<form method="GET" class="row g-2 align-items-end">
    <div class="col-md-6">
        <label class="form-label">Search by Reg / Name</label>
        <input class="form-control" name="search" value="{{ $search }}" placeholder="Registration number or candidate name">
    </div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
            <option value="all" @selected($status === 'all')>All</option>
            <option value="adjusted" @selected($status === 'adjusted')>Has Adjustment History</option>
            <option value="unchanged" @selected($status === 'unchanged')>Unchanged</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
    <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('choice-optimization.manual-adjustment.index') }}">Reset</a></div>
</form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Reg</th><th>Name</th><th>Allocation Ready Choices</th><th>Status</th><th class="w-1"></th></tr></thead><tbody>
@forelse($rows as $row)<tr><td><strong>{{ $row->reg }}</strong></td><td>{{ $row->candidate_name }}</td><td style="min-width:360px">@include('choice-optimization.partials.choice-code-lane', [
    'codes' => (array)$row->final_choice_codes,
    'choiceCodeAbbrMap' => $choiceCodeAbbrMap,
    'badgeClass' => 'bg-blue-lt',
    'emptyText' => '—',
])</td><td>@if(in_array((int)$row->registration_id,$adjustedIds,true))<span class="badge bg-yellow-lt text-yellow">HAS ADJUSTMENT HISTORY</span>@else<span class="badge bg-secondary-lt">UNCHANGED</span>@endif</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('choice-optimization.manual-adjustment.show',$row->registration_id) }}">Open</a></td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">No candidates found.</td></tr>@endforelse
</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div></div>
@endsection
