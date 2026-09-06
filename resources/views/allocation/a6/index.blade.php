@extends('layouts.app')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">A6 — Reporting &amp; Export</h2>
                <div class="text-secondary">Final read-only publishing layer bound to the current A5 100% PASS result.</div>
            </div>
            <div class="col-auto ms-auto"><a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a></div>
        </div>
    </div>
</div>

<div class="page-body"><div class="container-xl">
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Reporting Readiness</h3>
        <div class="ms-auto"><span class="badge bg-{{ $gate['ready']?'success':'danger' }}-lt">{{ $gate['ready']?'READY / ACTIVE':'BLOCKED / INACTIVE' }}</span></div>
    </div>
    <div class="card-body">
        @if($gate['ready'])
        <div class="row g-3">
            <div class="col-md-3"><div class="text-secondary">A4 Source</div><div class="fw-bold">v{{ $gate['a4_version'] }}</div></div>
            <div class="col-md-3"><div class="text-secondary">A5 Validation</div><div class="fw-bold">v{{ $gate['a5_version'] }} · 100% PASS</div></div>
            <div class="col-md-3"><div class="text-secondary">Circular Version</div><div class="fw-bold">v{{ $gate['circular_version'] }}</div></div>
            <div class="col-md-3"><div class="text-secondary">A5 Finalized</div><div class="fw-bold">{{ $gate['a5_finalized_at']?->format('d-m-Y h:i A') }}</div></div>
        </div>
        @if($dispositionSnapshot)
        <hr class="my-3"><div class="row g-3 align-items-center"><div class="col-md-8"><strong>A5.5 Publication State</strong><div class="text-secondary small">ACTIVE {{ number_format($dispositionSnapshot['active']) }} · WITHHELD {{ number_format($dispositionSnapshot['withheld']) }} · CANCELLED {{ number_format($dispositionSnapshot['cancelled']) }} · Revision {{ number_format($dispositionSnapshot['revision']) }}</div></div><div class="col-md-4 text-md-end"><a class="btn btn-outline-primary" href="{{ route('allocation.disposition.index') }}">Open A5.5 Control</a></div></div>
        <div class="alert alert-warning mt-3 mb-0 py-2"><strong>Publication safety:</strong> public TXT/DOCX and default cadre publication views contain ACTIVE candidates only. WITHHELD/CANCELLED remain internal allocation evidence and are exposed only through explicit internal reporting/status fields.</div>
        @endif
        @else
        <div class="alert alert-warning mb-0"><strong>Reporting/Export is locked.</strong> {{ $gate['reason'] }}</div>
        @endif
    </div>
</div>

<div class="row row-cards mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body d-flex flex-column">
            <h3>Interactive Reporting</h3>
            <p class="text-secondary">Search candidates, open consolidated module-wise detail, or drill down from a cadre.</p>
            <div class="btn-list mt-auto">
                <a class="btn btn-primary {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('allocation.a6.candidates'):'#' }}">Candidate Search</a>
                <a class="btn btn-outline-primary {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('allocation.a6.cadres'):'#' }}">Cadre Drill-down</a>
                <a class="btn btn-outline-primary {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('allocation.a6.summary.short'):'#' }}">Short Summary</a>
                <a class="btn btn-outline-primary {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('allocation.a6.summary'):'#' }}">In-depth Summary</a>
            </div>
        </div></div>
    </div>

    <div class="col-md-4">
        <div class="card h-100"><div class="card-body d-flex flex-column">
            <h3>TXT Export</h3>
            <form method="POST" action="{{ route('allocation.a6.exports.txt') }}" class="d-flex flex-column h-100">@csrf
                <div class="mb-2"><label class="form-label">Mode</label><select class="form-select" name="mode"><option value="consolidated">Single Consolidated TXT</option><option value="cadre_zip">One TXT per Cadre ZIP</option></select></div>
                <div class="mb-2"><label class="form-label">Registrations per Line</label><input class="form-control" type="number" name="registrations_per_line" min="1" max="20" value="8"></div>
                <div class="mb-3"><label class="form-label">Report Title</label><input class="form-control" name="report_title" value="Final Cadre Allocation"></div>
                <button class="btn btn-primary mt-auto align-self-start" @disabled(!$gate['ready'])>Queue TXT Export</button>
            </form>
        </div></div>
    </div>

    <div class="col-md-4">
        <div class="card h-100"><div class="card-body d-flex flex-column">
            <h3>DOCX Publishing</h3>
            <p class="text-secondary">Fill the final notice template using cadre tags and allocation totals through the centralized export queue.</p>
            <a class="btn btn-primary mt-auto align-self-start {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('allocation.a6.docx'):'#' }}">Fill DOCX Template</a>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Excel Export</h3><div class="ms-auto text-secondary small">Queued generation · JSON progress polling</div></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-7">
                <div class="fw-bold mb-2">Predefined Final Reports</div>
                <div class="btn-list">
                    <form method="POST" action="{{ route('allocation.a6.exports.xlsx') }}">@csrf<input type="hidden" name="scope" value="tabulation_eligible"><button class="btn btn-success" @disabled(!$gate['ready'])>All Viva Passed / Tabulation-Eligible</button></form>
                    <form method="POST" action="{{ route('allocation.a6.exports.xlsx') }}">@csrf<input type="hidden" name="scope" value="allocated"><button class="btn btn-success" @disabled(!$gate['ready'])>Only Allocated Candidates</button></form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="fw-bold mb-2">Custom Combined Report</div>
                <div class="text-secondary small mb-2">Pick module-wise fields from Registration through A5 and generate one combined workbook.</div>
                <a class="btn btn-outline-success {{ $gate['ready']?'':'disabled' }}" href="{{ $gate['ready']?route('allocation.a6.exports.excel-builder'):'#' }}">Open Dynamic Excel Builder</a>
            </div>
        </div>

        @if($gate['ready'])
        <hr class="my-3">
        <form class="row g-2 align-items-end" method="POST" action="{{ route('allocation.a6.exports.xlsx') }}">@csrf
            <input type="hidden" name="scope" value="cadre">
            <div class="col-md-5"><label class="form-label">Specific Cadre</label><select class="form-select" name="cadre_code" required><option value="">Select cadre</option>@foreach($cadres as $row)@if($row['allocated']>0)<option value="{{ $row['code'] }}">{{ $row['code'] }} - {{ $row['abbr'] }} ({{ number_format($row['allocated']) }})</option>@endif @endforeach</select></div>
            <div class="col-auto"><button class="btn btn-success">Queue Selected Cadre Excel</button></div>
        </form>
        @endif
    </div>
</div>

@if($exportRuns->isNotEmpty())
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Recent Export Jobs</h3></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0">
        <thead><tr><th>Queued</th><th>Type</th><th>Scope</th><th>Status</th><th>Progress</th><th class="w-1">Action</th></tr></thead>
        <tbody>@foreach($exportRuns as $run)<tr>
            <td>{{ $run->queued_at?->format('d-m-Y h:i A') }}</td>
            <td>{{ $run->export_type }}</td>
            <td>{{ $run->scope ?: '—' }}</td>
            <td><span class="badge bg-{{ $run->status==='completed'?'success':($run->status==='failed'?'danger':'azure') }}-lt">{{ strtoupper($run->status) }}</span></td>
            <td>{{ (int)$run->progress_percent }}%</td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.a6.exports.show',$run) }}">Open</a></td>
        </tr>@endforeach</tbody>
    </table></div>
</div>
@endif

@if($audits->isNotEmpty())
<div class="card">
    <div class="card-header"><h3 class="card-title">Recent Export Audit</h3></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0">
        <thead><tr><th>Time</th><th>Type</th><th>Scope</th><th>File</th><th>Hash</th></tr></thead>
        <tbody>@foreach($audits as $audit)<tr><td>{{ $audit->generated_at?->format('d-m-Y h:i A') }}</td><td>{{ $audit->export_type }}</td><td>{{ $audit->scope }}</td><td>{{ $audit->file_name }}</td><td class="small"><code>{{ \Illuminate\Support\Str::limit($audit->file_hash,20) }}</code></td></tr>@endforeach</tbody>
    </table></div>
</div>
@endif
</div></div>
@endsection
