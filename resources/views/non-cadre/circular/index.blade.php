@extends('layouts.app')
@section('title', 'NC1 — Non-Cadre Circular')
@section('page-header')
<div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Non Cadre Processing</div><h2 class="page-title">NC1 — Non-Cadre Circular</h2></div><div class="col-auto"><a href="{{ route('non-cadre.index') }}" class="btn btn-outline-secondary">Back to Non-Cadre Processing</a></div></div>
@endsection
@section('content')
@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if($errors->any()) <div class="alert alert-danger"><strong>Action could not be completed.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(!($gate['ready'] ?? false)) <div class="alert alert-warning"><strong>NC1 is locked.</strong> {{ $gate['reason'] }}</div> @endif
<div class="row row-cards mb-3">
 <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="subheader mb-2">Status / Version</div><div class="d-flex align-items-center gap-2 flex-wrap">@if($effective)<span class="badge bg-green-lt">{{ strtoupper($effective->status) }}</span><span class="h2 mb-0">v{{ $effective->version }}</span>@else<span class="badge bg-secondary-lt">NOT FINALIZED</span><span class="h2 mb-0">—</span>@endif</div></div></div></div>
 <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="subheader">Total Post</div><div class="h2 mb-0">{{ number_format($effective?->total_seats ?? 0) }}</div></div></div></div>
 <div class="col-md-6"><div class="card h-100"><div class="card-body"><div class="subheader mb-2">Grade-wise Post Count</div><div class="d-flex flex-wrap gap-2">@forelse($effectiveGradeCounts as $grade => $count)<span class="badge bg-azure-lt">Grade {{ $grade }}: {{ number_format($count) }}</span>@empty<span class="text-secondary">No finalized Circular data.</span>@endforelse</div></div></div></div>
</div>
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Import Circular</h3></div><div class="card-body">
<p class="text-secondary">Download the authority template, complete the post rows, then upload it. Only <strong>ACTIVE</strong> posts are accepted. Blank Bachelor Subject Codes means all subjects; otherwise use pipe-separated codes such as <code>121|124|156</code>.</p>
<div class="d-flex gap-2 flex-wrap mb-3"><a class="btn btn-outline-primary" href="{{ route('non-cadre.circular.template') }}">Download Excel Template</a></div>
<form method="post" action="{{ route('non-cadre.circular.import.upload') }}" enctype="multipart/form-data">@csrf<div class="row g-2"><div class="col-md"><input type="file" name="file" class="form-control" accept=".xlsx,.xls" required @disabled(!($gate['ready'] ?? false))></div><div class="col-md-auto"><button class="btn btn-primary" @disabled(!($gate['ready'] ?? false))>Upload & Validate</button></div></div></form>
</div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Circular Version History</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Version</th><th>Status</th><th>Posts</th><th>Seats</th><th>Source</th><th>Finalized</th><th></th></tr></thead><tbody>
@forelse($versions as $v)<tr><td class="fw-bold">v{{ $v->version }}</td><td><span class="badge {{ $v->status === 'finalized' ? 'bg-green-lt' : ($v->status === 'outdated' ? 'bg-secondary-lt' : 'bg-yellow-lt') }}">{{ strtoupper($v->status) }}</span></td><td>{{ $v->total_posts }}</td><td>{{ $v->total_seats }}</td><td>{{ $v->source_filename }}</td><td>{{ $v->finalized_at ?? '—' }}</td><td class="text-end"><a href="{{ route('non-cadre.circular.version', $v->id) }}" class="btn btn-sm btn-outline-primary">View</a></td></tr>@empty<tr><td colspan="7" class="text-secondary text-center py-4">No Non-Cadre Circular has been imported yet.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
