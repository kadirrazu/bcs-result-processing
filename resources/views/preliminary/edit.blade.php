@extends('layouts.app')
@section('title', 'Edit Preliminary Result')
@section('content')
@php
    $currentStatus = $result->candidate_status instanceof \BackedEnum ? $result->candidate_status->value : (string) $result->candidate_status;
@endphp
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title">Edit Preliminary Result</h2><div class="text-secondary">The previous values and your reason for the change are kept in the audit history.</div></div><div class="col-auto ms-auto"><a href="{{ route('preliminary.results.index') }}" class="btn btn-outline-secondary">Back to Results</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="alert alert-info">Only candidates with <strong>Active</strong> status take part in Preliminary result processing. Cancelled, Withheld and Expelled candidates remain on record but stay outside the processing pipeline. A warning does not exclude an Active candidate.</div>
<div class="row row-cards"><div class="col-lg-7"><div class="card"><div class="card-header"><h3 class="card-title">Candidate</h3></div><div class="card-body">
<dl class="row"><dt class="col-4">REG</dt><dd class="col-8">{{ $result->reg }}</dd><dt class="col-4">USER</dt><dd class="col-8">{{ $result->user_id }}</dd><dt class="col-4">Name</dt><dd class="col-8">{{ $registration?->name ?? '—' }}</dd><dt class="col-4">Category</dt><dd class="col-8">{{ match((int)($registration?->cadre_category ?? 0)){1=>'1 - GG',2=>'2 - TT',3=>'3 - GT',default=>'—'} }}</dd></dl>
<form method="post" action="{{ route('preliminary.results.update',$result) }}">@csrf @method('PUT')
<div class="mb-3"><label class="form-label">Mark</label><input name="mark" class="form-control" value="{{ old('mark',$result->mark) }}" placeholder="Enter the Preliminary mark"></div>
<div class="mb-3"><label class="form-label">Processing Status <span class="text-danger">*</span></label><select name="status" class="form-select" required>@foreach($statusOptions as $statusOption)<option value="{{ $statusOption }}" @selected(old('status', $currentStatus) === $statusOption)>{{ ucfirst($statusOption) }}</option>@endforeach</select><div class="form-hint">Only Active candidates are included in cut-off and final result processing.</div></div>
<div class="mb-3"><label class="form-label">Source Note</label><textarea name="source_note" rows="4" class="form-control" placeholder="Optional note carried from the source data">{{ old('source_note',$result->raw_candidate_status) }}</textarea><div class="form-hint">This note is kept for reference. It does not automatically change the processing status.</div></div>
<div class="mb-3"><label class="form-label">Reason for Change <span class="text-danger">*</span></label><textarea name="reason" rows="3" class="form-control" required>{{ old('reason') }}</textarea><div class="form-hint">Required for every manual correction. The reason is kept in both the database audit history and the Preliminary log.</div></div>
<button class="btn btn-primary">Save Changes</button></form></div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-header"><h3 class="card-title">Edit History</h3></div><div class="table-responsive"><table class="table table-sm card-table"><thead><tr><th>Time</th><th>Actor</th><th>Reason</th></tr></thead><tbody>@forelse($audits as $audit)<tr><td>{{ $audit->created_at?->format('d-m-Y h:i A') }}</td><td>{{ $audit->actor_name ?? $audit->actor_id }}</td><td>{{ $audit->reason ?: '—' }}</td></tr>@empty<tr><td colspan="3" class="text-center text-secondary">No manual edits yet.</td></tr>@endforelse</tbody></table></div></div></div></div>
</div></div>
@endsection
