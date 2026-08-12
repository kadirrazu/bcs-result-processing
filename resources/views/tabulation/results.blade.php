@extends('layouts.app')
@section('content')
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

        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Tabulated</div><div class="h2 mb-1">{{ number_format($reviewSummary['total']) }}</div><div class="small text-secondary">Viva appeared population</div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Viva PASS / FAIL</div><div class="h2 mb-1">{{ number_format($reviewSummary['viva_pass']) }} / {{ number_format($reviewSummary['viva_fail']) }}</div><div class="small text-secondary">Within tabulated population</div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">General / Technical Merit Eligible</div><div class="h2 mb-1">{{ number_format($reviewSummary['general_merit_eligible']) }} / {{ number_format($reviewSummary['technical_merit_eligible']) }}</div><div class="small text-secondary">Both: {{ number_format($reviewSummary['both_merit_eligible']) }}</div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Valid / Warning / Error</div><div class="h2 mb-1">{{ number_format($reviewSummary['valid']) }} / {{ number_format($reviewSummary['warning']) }} / {{ number_format($reviewSummary['error']) }}</div><div class="small text-secondary">Errors block finalization</div></div></div></div>
        </div>

        <div class="row row-cards mb-3">
            @foreach(['GG','GN','TT','T','GT'] as $trackCode)
                <div class="col"><div class="card h-100"><div class="card-body py-3"><div class="text-secondary">Track {{ $trackCode }}</div><div class="h3 mb-0">{{ number_format($reviewSummary['tracks'][$trackCode]) }}</div></div></div></div>
            @endforeach
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Reconciliation Summary</h3></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="text-secondary">General-only Merit Eligible</div><div class="fw-semibold">{{ number_format($reviewSummary['general_only_merit_eligible']) }}</div></div>
                    <div class="col-md-3"><div class="text-secondary">Technical-only Merit Eligible</div><div class="fw-semibold">{{ number_format($reviewSummary['technical_only_merit_eligible']) }}</div></div>
                    <div class="col-md-3"><div class="text-secondary">Both Merit Eligible</div><div class="fw-semibold">{{ number_format($reviewSummary['both_merit_eligible']) }}</div></div>
                    <div class="col-md-3"><div class="text-secondary">Not Merit Eligible</div><div class="fw-semibold">{{ number_format($reviewSummary['not_merit_eligible']) }}</div></div>
                    <div class="col-md-3"><div class="text-secondary">General High Grand Total Warning</div><div class="fw-semibold">{{ number_format($reviewSummary['general_high_warning']) }}</div></div>
                    <div class="col-md-3"><div class="text-secondary">Technical High Grand Total Warning</div><div class="fw-semibold">{{ number_format($reviewSummary['technical_high_warning']) }}</div></div>
                    <div class="col-md-6"><div class="text-secondary">Run Consistency</div><div class="fw-semibold">{{ $reviewSummary['total']===(int)$run->processed_rows ? 'Processed row count reconciled' : 'REVIEW REQUIRED: processed count mismatch' }}</div></div>
                </div>
            </div>
        </div>

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
                            <td><span class="badge bg-azure-lt">{{ $r->written_qualified_track }}</span></td>
                            <td>{{ $r->general_written_total ?? '—' }}</td>
                            <td>{{ $r->technical_written_total ?? '—' }}</td>
                            <td>{{ $r->viva_mark }}</td>
                            <td>{{ str_replace('TRACK FAILED', 'TRACK_FAILED', $r->generalGrandTotalDisplay()) }} / {{ str_replace('TRACK FAILED', 'TRACK_FAILED', $r->technicalGrandTotalDisplay()) }}</td>
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
