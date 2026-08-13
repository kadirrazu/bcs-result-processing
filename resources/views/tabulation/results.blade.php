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
<div class="page-header">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Tabulation Review · Version {{ $run->processing_version }}</h2>
                <div class="text-secondary">Only ACTIVE Viva APPEARED candidates are tabulated. Review derived values before finalization.</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('tabulation.index') }}">Back</a>
                @if($state?->status==='finalized'&&!$state?->is_stale&&$state?->latest_run_id===$run->id)
                    <a class="btn btn-outline-success" href="{{ route('tabulation.export.xlsx') }}">Export All XLSX</a>
                @endif
            </div>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container-xl">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @if($state?->is_stale)<div class="alert alert-warning"><strong>STALE:</strong> {{ $state->stale_reason }}</div>@endif

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Tabulated Population</h3>
                    <div class="card-subtitle">Counts below describe the finalized Tabulation population; they are not Merit ranks or Merit-eligible counts.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><div class="text-secondary">Total Tabulated</div><div class="h2 mb-1">{{ number_format($reviewSummary['total']) }}</div><div class="small text-secondary">ACTIVE Viva APPEARED candidates</div></div>
                    <div class="col-md-4"><div class="text-secondary">Viva PASS / FAIL</div><div class="h2 mb-1">{{ number_format($reviewSummary['viva_pass']) }} / {{ number_format($reviewSummary['viva_fail']) }}</div><div class="small text-secondary">Within the tabulated population</div></div>
                    <div class="col-md-4"><div class="text-secondary">VALID / WARNING / ERROR</div><div class="h2 mb-1">{{ number_format($reviewSummary['valid']) }} / {{ number_format($reviewSummary['warning']) }} / {{ number_format($reviewSummary['error']) }}</div><div class="small text-secondary">ERROR blocks finalization</div></div>
                </div>
                <div class="border-top pt-3">
                    <div class="text-secondary fw-semibold mb-2">Population by Written Qualified Track</div>
                    <div class="row g-2">
                        @foreach(['GG','GN','TT','T','GT'] as $trackCode)
                            <div class="col-6 col-md">
                                <div class="border rounded p-2 h-100">
                                    <span class="badge {{ $trackBadge($trackCode) }} mb-2">{{ $trackCode }}</span>
                                    <div class="h3 mb-0">{{ number_format($reviewSummary['tracks'][$trackCode]) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Merit Eligibility Outcome</h3>
                    <div class="card-subtitle">Eligibility is derived after the Viva gate. This section explains why track population and Merit-eligible counts may differ.</div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-secondary">General-only Tracks</div>
                            <div class="small mb-2"><span class="badge {{ $trackBadge('GG') }}">GG</span> + <span class="badge {{ $trackBadge('GN') }}">GN</span> population: <strong>{{ number_format($reviewSummary['general_only_track_population']) }}</strong></div>
                            <div class="h3 mb-1 text-success">{{ number_format($reviewSummary['general_only_merit_eligible']) }} eligible</div>
                            <div class="small text-danger">{{ number_format($reviewSummary['general_only_not_merit_eligible']) }} NOT_MERIT_ELIGIBLE</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-secondary">Technical-only Tracks</div>
                            <div class="small mb-2"><span class="badge {{ $trackBadge('TT') }}">TT</span> + <span class="badge {{ $trackBadge('T') }}">T</span> population: <strong>{{ number_format($reviewSummary['technical_only_track_population']) }}</strong></div>
                            <div class="h3 mb-1 text-success">{{ number_format($reviewSummary['technical_only_merit_eligible']) }} eligible</div>
                            <div class="small text-danger">{{ number_format($reviewSummary['technical_only_not_merit_eligible']) }} NOT_MERIT_ELIGIBLE</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-secondary">Both-track Candidates</div>
                            <div class="small mb-2"><span class="badge {{ $trackBadge('GT') }}">GT</span> population: <strong>{{ number_format($reviewSummary['both_track_population']) }}</strong></div>
                            <div class="h3 mb-1 text-success">{{ number_format($reviewSummary['both_merit_eligible']) }} eligible in both</div>
                            <div class="small text-danger">{{ number_format($reviewSummary['both_not_merit_eligible']) }} NOT_MERIT_ELIGIBLE</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-secondary">Overall Not Merit Eligible</div>
                            <div class="h2 mb-1 text-danger">{{ number_format($reviewSummary['not_merit_eligible']) }}</div>
                            <div class="small text-secondary">Usually Viva FAIL within the APPEARED population.</div>
                            <div class="small mt-2">General overall eligible: <strong>{{ number_format($reviewSummary['general_merit_eligible']) }}</strong></div>
                            <div class="small">Technical overall eligible: <strong>{{ number_format($reviewSummary['technical_merit_eligible']) }}</strong></div>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3"><div class="text-secondary">General High Grand Total Warning</div><div class="fw-semibold">{{ number_format($reviewSummary['general_high_warning']) }}</div></div>
                    <div class="col-md-3"><div class="text-secondary">Technical High Grand Total Warning</div><div class="fw-semibold">{{ number_format($reviewSummary['technical_high_warning']) }}</div></div>
                    <div class="col-md-6"><div class="text-secondary">Run Consistency</div><div class="fw-semibold">{{ $reviewSummary['total']===(int)$run->processed_rows ? 'PROCESSED_ROW_COUNT_RECONCILED' : 'REVIEW_REQUIRED_PROCESSED_COUNT_MISMATCH' }}</div></div>
                </div>
            </div>
        </div>

        @if($hashIntegrity['stored_hash'] ?? null)
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">Finalized Dataset Integrity</h3></div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-3"><div class="text-secondary">Integrity Status</div><div class="mt-1"><span class="badge bg-{{ $hashIntegrity['verified'] ? 'success' : 'danger' }}-lt">{{ $hashIntegrity['status'] }}</span></div></div>
                        <div class="col-md-3"><div class="text-secondary">Run / Version</div><div class="fw-semibold">#{{ $hashIntegrity['processing_run_id'] ?? '—' }} / v{{ $hashIntegrity['processing_version'] ?? '—' }}</div></div>
                        <div class="col-md-6"><div class="text-secondary">Finalized Dataset Hash (SHA-256)</div><code class="small text-break user-select-all">{{ $hashIntegrity['stored_hash'] }}</code></div>
                    </div>
                    <div class="small text-secondary mt-2">{{ $hashIntegrity['detail'] }}</div>
                </div>
            </div>
        @endif

        <form class="card card-body mb-3">
            @if(request('run'))<input type="hidden" name="run" value="{{ request('run') }}">@endif
            <div class="row g-2">
                <div class="col-md-3"><input class="form-control" name="search" value="{{ $search }}" placeholder="Name, REG or USER"></div>
                <div class="col-md-2"><select class="form-select" name="track"><option value="">All tracks</option>@foreach(['GG','GN','TT','T','GT'] as $v)<option value="{{ $v }}" @selected($track===$v)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-md-2"><select class="form-select" name="status"><option value="">All validation</option>@foreach(['valid','warning','error'] as $v)<option value="{{ $v }}" @selected($status===$v)>{{ strtoupper($v) }}</option>@endforeach</select></div>
                <div class="col-md-2"><select class="form-select" name="merit"><option value="">All merit eligibility</option><option value="general" @selected($merit==='general')>General only</option><option value="technical" @selected($merit==='technical')>Technical only</option><option value="both" @selected($merit==='both')>Both</option><option value="none" @selected($merit==='none')>None</option></select></div>
                <div class="col-md-2"><select class="form-select" name="warning"><option value="">All warnings</option><option value="general_high" @selected($warning==='general_high')>General high grand total</option><option value="technical_high" @selected($warning==='technical_high')>Technical high grand total</option></select></div>
                <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
                <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('tabulation.results', request('run') ? ['run'=>request('run')] : []) }}">Reset</a></div>
            </div>
        </form>

        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead><tr><th>Candidate</th><th>Track</th><th>General Written</th><th>Technical Written</th><th>Viva</th><th>Grand Total (G/T)</th><th>P/F (G/T)</th><th>Merit Eligible (G/T)</th><th>Validation</th><th></th></tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td><strong>{{ $r->reg }}</strong><br><span>{{ $r->candidate_name ?: '—' }}</span><br><span class="text-secondary small">{{ $r->user_id }}</span></td>
                            <td><span class="badge {{ $trackBadge((string) $r->written_qualified_track) }}">{{ strtoupper((string) $r->written_qualified_track) }}</span></td>
                            <td>{{ $r->general_written_total ?? '—' }}</td>
                            <td>{{ $r->technical_written_total ?? '—' }}</td>
                            <td>{{ $r->viva_mark }}</td>
                            <td>{{ $r->generalGrandTotalDisplay() }} / {{ $r->technicalGrandTotalDisplay() }}</td>
                            <td>
                                <span class="text-{{ strtoupper((string) $r->general_pf)==='PASS' ? 'success' : (strtoupper((string) $r->general_pf)==='FAIL' ? 'danger' : 'secondary') }} fw-bold">{{ strtoupper((string) $r->general_pf) }}</span>
                                /
                                <span class="text-{{ strtoupper((string) $r->technical_pf)==='PASS' ? 'success' : (strtoupper((string) $r->technical_pf)==='FAIL' ? 'danger' : 'secondary') }} fw-bold">{{ strtoupper((string) $r->technical_pf) }}</span>
                            </td>
                            <td>{{ $r->general_merit_eligible?'YES':'NO' }} / {{ $r->technical_merit_eligible?'YES':'NO' }}</td>
                            <td><span class="badge bg-{{ $r->validation_status==='error'?'danger':($r->validation_status==='warning'?'warning':'success') }}-lt">{{ strtoupper($r->validation_status) }}</span>@if($r->review_warnings)<div class="small text-secondary mt-1">{{ implode(', ',(array)$r->review_warnings) }}</div>@endif @if($r->validation_errors)<div class="small text-danger mt-1">{{ implode(', ',(array)$r->validation_errors) }}</div>@endif</td>
                            <td>@if($state?->status==='finalized'&&$state?->latest_run_id===$r->processing_run_id&&!$state?->is_stale)<a class="btn btn-sm btn-outline-primary" href="{{ route('tabulation.show',$r) }}">Finalized View</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-secondary py-4">No Tabulation rows match the selected filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center"><div class="text-secondary">Displaying {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} records</div><div>{{ $rows->links() }}</div></div>
        </div>

        @if($run->status==='completed'&&$run->error_rows===0&&(!($state?->status==='finalized')||$state?->latest_run_id!==$run->id)&&!$state?->is_stale)
            <div class="card">
                <div class="card-header"><h3 class="card-title">Finalize Tabulation</h3></div>
                <div class="card-body">
                    <div class="alert alert-info">Warnings are review-only and do not block finalization. Blocking validation errors must remain zero.</div>
                    <form method="POST" action="{{ route('tabulation.finalize') }}">@csrf<div class="row g-2"><div class="col-md-3"><input class="form-control" name="confirmation" placeholder="Type FINALIZE"></div><div class="col-md-6"><input class="form-control" name="notes" placeholder="Optional finalization notes"></div><div class="col-auto"><button class="btn btn-success">Finalize</button></div></div></form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
