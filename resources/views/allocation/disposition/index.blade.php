@extends('layouts.app')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">A5.5 — Result Disposition / Publication Control</h2><div class="text-secondary">Post-allocation publication control. WITHHELD/CANCELLED never releases a seat and never reruns Allocation.</div></div><div class="col-auto ms-auto"><a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row row-cards mb-3">
@foreach([['Final Allocated',$a5->total_allocated,'blue'],['Active',$snapshot['active'],'success'],['Withheld',$snapshot['withheld'],'warning'],['Cancelled',$snapshot['cancelled'],'danger']] as $card)
<div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">{{ $card[0] }}</div><div class="h1 mb-0">{{ number_format($card[1]) }}</div></div></div></div>
@endforeach
</div>
<div class="alert alert-info"><strong>Publication safety rule:</strong> ACTIVE is the default for every finalized A5 allocated candidate. WITHHELD/CANCELLED candidates remain allocated internally, their seats are not released, but A6 public TXT/DOCX/cadre publication lists must exclude them.</div>
<div class="card">
<div class="card-body"><form method="GET" class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label">Search</label><input class="form-control" name="search" value="{{ $search }}" placeholder="Reg / User ID / Name"></div><div class="col-md-3"><label class="form-label">Allocation Status</label><select class="form-select" name="status"><option value="ALL" @selected($status==='ALL')>All</option><option value="ACTIVE" @selected($status==='ACTIVE')>ACTIVE</option><option value="WITHHELD" @selected($status==='WITHHELD')>WITHHELD</option><option value="CANCELLED" @selected($status==='CANCELLED')>CANCELLED</option></select></div><div class="col-auto"><button class="btn btn-primary">Filter</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('allocation.disposition.index') }}">Reset</a></div></form></div>
<div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Reg</th><th>Name</th><th>Allocated Cadre</th><th>Status</th><th>Reason</th><th>Operator</th><th>Changed</th><th></th></tr></thead><tbody>
@forelse($rows as $row) @php($effective = strtoupper((string)($row->disposition_status ?: 'ACTIVE'))) <tr><td><strong>{{ $row->reg }}</strong></td><td>{{ $row->candidate_name }}</td><td>{{ $row->cadre_code }} - {{ $abbr->get((int)$row->cadre_code,'—') }}</td><td><span class="badge bg-{{ $effective==='ACTIVE'?'success':($effective==='WITHHELD'?'warning':'danger') }}-lt">{{ $effective }}</span></td><td class="text-wrap" style="max-width:360px">{{ $row->disposition_reason ?: '—' }}</td><td>@if($row->disposition_changed_by){{ $row->disposition_changed_by }} - {{ $operators->get($row->disposition_changed_by,'Unknown') }}@else System Default @endif</td><td>{{ $row->disposition_changed_at ? \Carbon\Carbon::parse($row->disposition_changed_at)->format('d-m-Y h:i A') : '—' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.disposition.show',$row->registration_id) }}">Review / Change</a></td></tr>
@empty<tr><td colspan="8" class="text-center text-secondary py-4">No allocated candidates matched.</td></tr>@endforelse
</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div></div></div></div>
@endsection
