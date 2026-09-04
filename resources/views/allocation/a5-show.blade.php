@extends('layouts.app')
@section('content')
<style>
    .a5-summary-table th,.a5-summary-table td{text-align:center;vertical-align:middle;padding:.45rem .5rem}
    .a5-header-actions{margin-left:auto;display:flex;justify-content:flex-end;align-items:center;gap:.4rem;flex-wrap:nowrap}
    .a5-header-actions .btn{padding:.3rem .55rem;font-size:.78rem;line-height:1.25;white-space:nowrap}
    .a5-header-actions form{margin:0}
    .a5-summary-table .a5-group-row td{text-align:left!important;font-weight:700;background:var(--tblr-bg-surface-secondary)}
    .a5-summary-table .a5-cadre-cell{text-align:left!important}
    .a5-summary-table .a5-column-header th{font-weight:700}
</style>
<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-3 flex-nowrap">
    <div>
        <h2 class="page-title">A5 - Allocated Candidate & Final Cadre Seat-Limit Validation</h2>
        <div class="text-secondary">Independent read-only assurance of final A4 allocation eligibility and sanctioned seat limits.</div>
    </div>
    <div class="a5-header-actions">
        <a class="btn btn-primary" href="{{ route('allocation.a5.candidates',$a5Run) }}">Candidate Validity Report</a>
        @if($a5Run->status === 'validated_ok' && !(bool)$a5Run->is_stale)
            <form method="POST" action="{{ route('allocation.a5.finalize',$a5Run) }}" class="m-0">@csrf<button class="btn btn-success" type="submit">Finalize A5 — 100% PASS</button></form>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a>
    </div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if((bool)$a5Run->is_stale)<div class="alert alert-warning"><strong>STALE / OUTDATED.</strong> {{ $a5Run->stale_reason }}</div>@endif

<div class="row row-cards mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Source Data Version</div><div class="fw-semibold">A4 Phase-2 v{{ $a5Run->a4Run?->version }}</div><div class="small text-secondary">Circular v{{ $a5Run->circular_version }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Validation Version</div><div class="h2 mb-0">A5 v{{ $a5Run->version }}</div><div class="small text-secondary">{{ $a5Run->status === 'finalized' ? 'Finalized validation evidence' : 'Pre-finalized validation evidence' }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Candidate Validation</div><div class="fw-semibold text-success">PASS {{ number_format($a5Run->candidate_passed) }}</div><div class="small {{ $a5Run->candidate_failed ? 'text-danger' : 'text-secondary' }}">FAIL {{ number_format($a5Run->candidate_failed) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Cadre Seat-Limit</div><div class="fw-semibold">Checked {{ number_format($a5Run->capacity_checked) }}</div><div class="small {{ $a5Run->capacity_failed ? 'text-danger' : 'text-secondary' }}">FAIL {{ number_format($a5Run->capacity_failed) }}</div></div></div></div>
</div>

<div class="alert alert-{{ $a5Run->status === 'finalized' ? 'success' : ($a5Run->candidate_failed==0 && $a5Run->capacity_failed==0 ? 'azure' : 'danger') }}">
    <strong>Gate Status:</strong> {{ strtoupper(str_replace('_',' ',$a5Run->status)) }}.
    @if($a5Run->candidate_failed==0 && $a5Run->capacity_failed==0)
        Candidate validity and cadre seat-limit validation are 100% PASS.
    @else
        Reporting/Export remains BLOCKED until all A5 failures are resolved upstream and A5 is re-run.
    @endif
</div>

<div class="card mb-3">
    <div class="card-header"><div>
        <h3 class="card-title">A5 - Allocated Candidate & Final Cadre Seat-Limit Validation</h3>
        <div class="card-subtitle">Circular group/serial order. Click a cadre to review its allocated-candidate validation details.</div>
    </div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a5-summary-table"><tbody>
    @php $lastGroup = null; @endphp
    @forelse($capacityResults as $row)
        @php
            $entry = $entries->get((int)$row->circular_entry_id);
            $type = (string)($entry?->cadre_type?->value ?? $entry?->cadre_type ?? '');
            $groupLabel = $type==='GG' ? 'General Cadre' : ($type==='TT' ? 'Technical / Professional Cadre' : 'Other');
            $serial = $entry ? ((string)$entry->cadre_serial.($entry->sub_serial!==null ? '.'.(string)$entry->sub_serial : '')) : '—';
            $abbr = (string)$abbreviationByCode->get((int)$row->cadre_code,'—');
            $stats = $candidateStats->get((int)$row->circular_entry_id);
            $candidateTotal = (int)($stats?->total_candidates ?? 0);
            $candidatePassed = (int)($stats?->passed_candidates ?? 0);
            $candidateFailed = (int)($stats?->failed_candidates ?? 0);
            $candidateStatus = $candidateTotal === 0 ? 'N/A' : ($candidateFailed === 0 && $candidatePassed === $candidateTotal ? 'PASS' : 'FAIL');
            $overallStatus = ($candidateStatus === 'FAIL' || $row->status !== 'PASS') ? 'FAIL' : 'PASS';
        @endphp
        @if($lastGroup !== $groupLabel)
            <tr class="a5-group-row"><td colspan="8">{{ $groupLabel }}</td></tr>
            <tr class="a5-column-header"><th>SL</th><th class="text-start">Cadre</th><th>Seat Limit</th><th>Final Allocated</th><th>Remaining</th><th>Candidate Validation</th><th>Seat Limit Validation</th><th>Overall</th></tr>
            @php $lastGroup = $groupLabel; @endphp
        @endif
        <tr>
            <td>{{ $serial }}</td>
            <td class="a5-cadre-cell"><a class="fw-bold text-decoration-none" href="{{ route('allocation.a5.cadre-results',[$a5Run,$entry]) }}">{{ $row->cadre_code }} - {{ $abbr }}</a></td>
            <td>{{ number_format($row->sanctioned_posts) }}</td>
            <td><strong>{{ number_format($row->allocated_count) }}</strong></td>
            <td class="{{ $row->remaining_posts < 0 ? 'text-danger fw-bold' : '' }}">{{ number_format($row->remaining_posts) }}</td>
            <td><span class="badge bg-{{ $candidateStatus==='PASS'?'success':($candidateStatus==='FAIL'?'danger':'secondary') }}-lt">{{ $candidateStatus }}</span><div class="small text-secondary mt-1">{{ number_format($candidatePassed) }}/{{ number_format($candidateTotal) }} PASS</div></td>
            <td><span class="badge bg-{{ $row->status==='PASS'?'success':'danger' }}-lt">{{ $row->status }}</span>@if($row->reason_code)<div class="small text-danger mt-1">{{ $row->reason_code }}</div>@endif</td>
            <td><span class="badge bg-{{ $overallStatus==='PASS'?'success':'danger' }}-lt">{{ $overallStatus }}</span></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-secondary py-4">No A5 validation rows.</td></tr>
    @endforelse
    </tbody></table></div>
</div>

<div class="card"><div class="card-header"><h3 class="card-title">Source / Validation Provenance</h3></div><div class="card-body">
    <div class="row g-3">
        <div class="col-md-6"><div class="small text-secondary">A4 Phase-2 Source Version / Hash</div><div class="fw-semibold">v{{ $a5Run->a4Run?->version }}</div><code class="text-break">{{ $a5Run->a4_output_hash }}</code></div>
        <div class="col-md-6"><div class="small text-secondary">Circular Source Version / Hash</div><div class="fw-semibold">v{{ $a5Run->circular_version }}</div><code class="text-break">{{ $a5Run->circular_hash }}</code></div>
        <div class="col-md-6"><div class="small text-secondary">Authoritative Registration Eligibility Fingerprint</div><code class="text-break">{{ $a5Run->registration_hash }}</code></div>
        <div class="col-md-6"><div class="small text-secondary">A5 Validation Version / Evidence Hashes</div><div class="fw-semibold">v{{ $a5Run->version }}</div><div><code class="text-break">Candidate: {{ $a5Run->candidate_result_hash }}</code></div><div><code class="text-break">Capacity: {{ $a5Run->capacity_result_hash }}</code></div></div>
    </div>
</div></div>
</div></div>
@endsection
