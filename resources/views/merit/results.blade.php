@extends('layouts.app')

@section('content')
@php
    $trackBadge = static fn (string $track): string => match ($track) {
        'GG' => 'bg-blue-lt text-blue',
        'GN' => 'bg-azure-lt text-azure',
        'TT' => 'bg-purple-lt text-purple',
        'T' => 'bg-indigo-lt text-indigo',
        'GT' => 'bg-teal-lt text-teal',
        default => 'bg-secondary-lt text-secondary',
    };
@endphp
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Merit Review · Version {{ $run->processing_version }}</h2><div class="text-secondary">Unique sequential Common, General, Technical and cadre-wise merit.</div></div><div class="col-auto d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('merit.index') }}">Back</a>@if($state->status==='finalized'&&!$state->is_stale&&$state->latest_run_id===$run->id)<a class="btn btn-outline-success" href="{{ route('merit.export.xlsx') }}">Export All XLSX</a>@endif</div></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($state->is_stale)<div class="alert alert-warning"><strong>STALE</strong> {{ $state->stale_reason }}</div>@endif

<div class="card mb-3"><div class="card-header"><div><h3 class="card-title">Merit Reconciliation Summary</h3><div class="card-subtitle">Generated ranking counts and candidate coverage for this Merit run.</div></div></div><div class="card-body">
<div class="row g-3 mb-3">
<div class="col-md-3"><div class="text-secondary">Tabulated Candidates</div><div class="h2 mb-0">{{ number_format($reviewSummary['total']) }}</div></div>
<div class="col-md-3"><div class="text-secondary">Common Merit Ranked</div><div class="h2 mb-0 text-success">{{ number_format($reviewSummary['common_ranked']) }}</div></div>
<div class="col-md-3"><div class="text-secondary">General Merit Ranked</div><div class="h2 mb-0">{{ number_format($reviewSummary['general_ranked']) }}</div></div>
<div class="col-md-3"><div class="text-secondary">Technical Merit Ranked</div><div class="h2 mb-0">{{ number_format($reviewSummary['technical_ranked']) }}</div></div>
</div>
<div class="row g-3 border-top pt-3">
<div class="col-md-3"><div class="text-secondary">NOT_MERIT_ELIGIBLE</div><div class="h3 mb-0 text-danger">{{ number_format($reviewSummary['not_merit_eligible']) }}</div></div>
<div class="col-md-3"><div class="text-secondary">Cadre-wise Lists</div><div class="h3 mb-0">{{ number_format($reviewSummary['cadre_count']) }}</div></div>
<div class="col-md-3"><div class="text-secondary">Cadre-wise Rank Rows</div><div class="h3 mb-0">{{ number_format($reviewSummary['cadre_rows']) }}</div></div>
<div class="col-md-3"><div class="text-secondary">Run Consistency</div><div class="fw-semibold">{{ $reviewSummary['total']===(int)$run->processed_rows ? 'PROCESSED_ROW_COUNT_RECONCILED' : 'REVIEW_REQUIRED_PROCESSED_COUNT_MISMATCH' }}</div></div>
</div>
<div class="mt-3 d-flex flex-wrap gap-2"><span class="text-secondary me-1">Qualified Track Population:</span>@foreach(['GG','GN','TT','T','GT'] as $tc)<span class="badge {{ $trackBadge($tc) }}">{{ $tc }} = {{ number_format($reviewSummary['track_'.strtolower($tc)]) }}</span>@endforeach</div>
</div></div>

@if($latestFinalization?->dataset_hash)
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Finalized Dataset Integrity</h3></div><div class="card-body"><div class="row g-3"><div class="col-md-3"><div class="text-secondary">Status</div><span class="badge bg-success-lt">HASH_VERIFIED_AT_FINALIZATION</span></div><div class="col-md-3"><div class="text-secondary">Version</div><div class="fw-semibold">v{{ $latestFinalization->processing_version }}</div></div><div class="col-md-6"><div class="text-secondary">Merit Dataset Hash (SHA-256)</div><code class="small text-break user-select-all">{{ $latestFinalization->dataset_hash }}</code></div></div></div></div>
@endif

<div class="card mb-3"><div class="card-header"><div><h3 class="card-title">Cadre-wise Merit Lists</h3><div class="card-subtitle">Sorted by cadre code. Format: CODE (ABBR) - candidate count.</div></div></div><div class="card-body d-flex flex-wrap gap-2">@forelse($cadres as $c)<a class="btn btn-sm btn-outline-primary" href="{{ route('merit.cadre',['cadreCode'=>$c->cadre_code,'run'=>$run->id]) }}">{{ $c->cadre_code }} ({{ $c->cadre_abbr }}) - {{ number_format($c->candidate_count) }}</a>@empty<span class="text-secondary">No cadre-wise merit list generated.</span>@endforelse</div></div>

<form class="card card-body mb-3"><input type="hidden" name="run" value="{{ $run->id }}"><div class="row g-2"><div class="col-md-4"><input class="form-control" name="search" value="{{ $search }}" placeholder="Name, REG or USER"></div><div class="col-md-2"><select class="form-select" name="track"><option value="">All tracks</option>@foreach(['GG','GN','TT','T','GT'] as $v)<option value="{{ $v }}" @selected($track===$v)>{{ $v }}</option>@endforeach</select></div><div class="col-md-3"><select class="form-select" name="scope"><option value="">All candidates</option><option value="common" @selected($scope==='common')>Common Merit Ranked</option><option value="general" @selected($scope==='general')>General Merit Ranked</option><option value="technical" @selected($scope==='technical')>Technical Merit Ranked</option><option value="not_eligible" @selected($scope==='not_eligible')>NOT_MERIT_ELIGIBLE</option></select></div><div class="col-auto"><button class="btn btn-primary">Filter</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('merit.results',['run'=>$run->id]) }}">Reset</a></div></div></form>

<div class="card mb-3"><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Candidate</th><th>Track</th><th>Common</th><th>General</th><th>Technical</th><th>all_merit_tech</th><th>Status</th><th></th></tr></thead><tbody>@forelse($rows as $r)<tr><td><strong>{{ $r->reg }}</strong><br><span>{{ $r->candidate_name ?: '—' }}</span><br><span class="text-secondary small">{{ $r->user_id }}</span></td><td><span class="badge {{ $trackBadge((string)$r->written_qualified_track) }}">{{ strtoupper((string)$r->written_qualified_track) }}</span></td><td>{{ $r->common_merit_position ?? '—' }}</td><td>{{ $r->general_merit_position ?? '—' }}</td><td>{{ $r->technical_merit_position ?? '—' }}</td><td><code>{{ \App\Models\MeritResult::allMeritTechJson($r->all_merit_tech) }}</code></td><td>{{ $r->status_reason ?? 'MERIT_RANKED' }}</td><td>@if(in_array($state->status,['review_ready','finalized'],true)&&!$state->is_stale&&$state->latest_run_id===$r->processing_run_id&&$run->status==='completed')<a class="btn btn-sm btn-outline-primary" href="{{ route('merit.show',$r) }}">{{ $state->status==='finalized' ? 'Finalized View' : 'Review View' }}</a>@endif</td></tr>@empty<tr><td colspan="8" class="text-center text-secondary py-4">No Merit rows match the selected filters.</td></tr>@endforelse</tbody></table></div><div class="card-footer d-flex justify-content-between align-items-center"><div class="text-secondary">Displaying {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} records</div><div>{{ $rows->links() }}</div></div></div>

@if($run->status==='completed'&&!$state->is_stale&&$state->status!=='finalized')<div class="card"><div class="card-header"><h3 class="card-title">Finalize Merit</h3></div><div class="card-body"><div class="alert alert-info">Finalization performs fresh Circular, Tabulation and Choice Validation hash verification and verifies the generated Merit dataset hash.</div><form method="POST" action="{{ route('merit.finalize') }}">@csrf<div class="row g-2"><div class="col-md-3"><input class="form-control" name="confirmation" placeholder="Type FINALIZE"></div><div class="col-md-6"><input class="form-control" name="notes" placeholder="Optional notes"></div><div class="col-auto"><button class="btn btn-success">Finalize</button></div></div></form></div></div>@endif
</div></div>
@endsection
