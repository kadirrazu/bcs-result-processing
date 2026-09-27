@extends('layouts.app')
@section('title','NC5 — Candidate Search')
@section('page-header')
<div class="row g-2 align-items-center">
    <div class="col"><div class="page-pretitle">NC5 · Interactive Reporting</div><h2 class="page-title">Non-Cadre Candidate Search</h2><div class="text-secondary">Current finalized NC4 frozen population. Search by Registration, User ID or Name and open the consolidated Non-Cadre authority view.</div></div>
    <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('non-cadre.reporting.index') }}">Back to NC5 Reporting</a></div>
</div>
@endsection
@section('content')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Frozen Population</div><div class="h1 mb-0">{{ number_format($totalCandidates) }}</div><div class="small text-secondary">Preserved NC4 input candidates</div></div></div></div>
    @if($search !== '')<div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Search Result</div><div class="h1 mb-0">{{ number_format($results->total()) }}</div><div class="small text-secondary">Matching candidates</div></div></div></div>@endif
</div>
<div class="card">
    <div class="card-body"><form class="row g-2" method="GET"><div class="col-md-6"><input class="form-control" name="search" value="{{ $search }}" placeholder="Search Reg / User ID / Name"></div><div class="col-auto"><button class="btn btn-primary">Search</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('non-cadre.reporting.candidates') }}">Reset</a></div></form></div>
    <div class="table-responsive"><table class="table table-bordered table-vcenter mb-0"><thead><tr><th class="text-center">SL</th><th>Reg</th><th>User</th><th>Name</th><th class="text-center">Common Merit</th><th>Non-Cadre Allocation</th><th class="w-1"></th></tr></thead><tbody>
    @forelse($results as $row)<tr><td class="text-center">{{ $results->firstItem()+$loop->index }}</td><td><strong>{{ $row->reg }}</strong></td><td>{{ $row->user_id }}</td><td>{{ $row->name }}</td><td class="text-center fw-bold">{{ $row->common_merit_position }}</td><td>@if($row->post_code)<span class="badge bg-success-lt">{{ $row->post_code }} · {{ $row->post_title }} · {{ strtoupper((string)$row->allocation_basis) }}</span>@elseif($row->historical_excluded)<span class="badge bg-warning-lt">HISTORICALLY EXCLUDED</span>@else<span class="badge bg-secondary-lt">NOT ALLOCATED</span>@endif</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('non-cadre.reporting.candidate',$row->reg) }}">Details</a></td></tr>
    @empty<tr><td colspan="7" class="text-center text-secondary py-4">No candidates found.</td></tr>@endforelse
    </tbody></table></div>
    @if($results->hasPages())<div class="card-footer">{{ $results->links() }}</div>@endif
</div>
@endsection
