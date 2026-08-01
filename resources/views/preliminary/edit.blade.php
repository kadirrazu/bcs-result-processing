@extends('layouts.app')
@section('title', 'Edit Preliminary Result')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title">Edit Preliminary Result</h2><div class="text-secondary">Every change requires a reason and creates immutable database + file audit records.</div></div><div class="col-auto ms-auto"><a href="{{ route('preliminary.results.index') }}" class="btn btn-outline-secondary">Back to Results</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row row-cards"><div class="col-lg-7"><div class="card"><div class="card-header"><h3 class="card-title">Candidate</h3></div><div class="card-body">
<dl class="row"><dt class="col-4">REG</dt><dd class="col-8">{{ $result->reg }}</dd><dt class="col-4">USER</dt><dd class="col-8">{{ $result->user_id }}</dd><dt class="col-4">Name</dt><dd class="col-8">{{ $registration?->name ?? '—' }}</dd><dt class="col-4">Category</dt><dd class="col-8">{{ match((int)($registration?->cadre_category ?? 0)){1=>'GG',2=>'TT',3=>'GT',default=>'—'} }}</dd></dl>
<form method="post" action="{{ route('preliminary.results.update',$result) }}">@csrf @method('PUT')
<div class="mb-3"><label class="form-label">Mark</label><input name="mark" class="form-control" value="{{ old('mark',$result->mark) }}" placeholder="Leave blank for cancelled"></div>
<div class="mb-3"><label class="form-label">Candidate Status Text</label><textarea name="candidate_status" rows="4" class="form-control" placeholder="Raw authority/source status text">{{ old('candidate_status',$result->raw_candidate_status) }}</textarea><div class="form-hint">If mark exists, mark is accepted and the status text is preserved. If mark is blank, candidate becomes CANCELLED.</div></div>
<div class="mb-3"><label class="form-label">Reason for Change <span class="text-danger">*</span></label><textarea name="reason" rows="3" class="form-control" required>{{ old('reason') }}</textarea><div class="form-hint">Required. This is stored permanently in database audit and preliminary file log.</div></div>
<button class="btn btn-primary">Save with Audit Trail</button>
</form></div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-header"><h3 class="card-title">Edit History</h3></div><div class="table-responsive"><table class="table table-sm card-table"><thead><tr><th>Time</th><th>Actor</th><th>Reason</th></tr></thead><tbody>@forelse($audits as $audit)<tr><td>{{ $audit->created_at?->format('d-m-Y h:i A') }}</td><td>{{ $audit->actor_name ?? $audit->actor_id }}</td><td>{{ $audit->reason ?: '—' }}</td></tr>@empty<tr><td colspan="3" class="text-center text-secondary">No manual edits yet.</td></tr>@endforelse</tbody></table></div></div></div></div>
</div></div>
@endsection
