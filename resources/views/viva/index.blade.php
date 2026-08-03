@extends('layouts.app')

@section('title', 'Viva Processing')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Viva Processing</h2>
                <div class="text-secondary">Candidate mapping → Board data → Review → Reconciliation → Viva result processing → Finalization</div>
            </div>
            <div class="col-auto ms-auto d-flex gap-2 flex-wrap">
                <a href="{{ route('viva.template.mapping') }}" class="btn btn-outline-secondary">Mapping Template</a>
                <a href="{{ route('viva.template.board') }}" class="btn btn-outline-secondary">Board Data Template</a>
            </div>
        </div>
    </div>
</div>

<div class="page-body"><div class="container-xl">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @if(!$writtenReady)
        <div class="alert alert-warning">
            <div class="fw-semibold">Finalized Written result is required before Viva candidate mapping can begin.</div>
            <div class="mt-1">The Viva Module will only accept candidates who are present in the current finalized Written-qualified result.</div>
        </div>
    @endif

    <div class="row row-cards mb-3">
        @foreach([
            'Written-qualified / Viva eligible' => $counts['written_eligible'],
            'Mapped candidates' => $counts['mapped'],
            'Board records' => $counts['results'],
            'Warnings' => $counts['warnings'],
            'Quota mismatches' => $counts['quota_mismatch'],
            'Source review flags' => $counts['source_review'],
            'High-mark review' => $counts['high_mark'],
        ] as $label => $value)
            <div class="col-sm-6 col-lg"><div class="card card-sm h-100"><div class="card-body d-flex flex-column justify-content-between" style="min-height: 96px;"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format($value) }}</div></div></div></div>
        @endforeach
    </div>


    <div class="card mb-3"><div class="card-header"><h3 class="card-title">Import Viva Candidate Mapping</h3></div><div class="card-body"><p class="text-secondary">Upload <code>user, reg, code</code>. Each candidate must already be in the current finalized Written-qualified result, and every Viva code must be unique.</p><form method="post" action="{{ route('viva.mapping.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf<div class="col-md-6"><label class="form-label">Candidate mapping file</label><input class="form-control" type="file" name="file" accept=".xlsx,.csv" required @disabled(!$writtenReady)></div><div class="col-auto"><button class="btn btn-primary" @disabled(!$writtenReady)>Upload &amp; Stage Mapping</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('viva.template.mapping') }}">Download Mapping Template</a></div></form></div></div>

    <div class="card mb-3"><div class="card-header"><h3 class="card-title">Import Viva Board Data</h3></div><div class="card-body"><p class="text-secondary">Upload board date, member, Viva code, mark/ABS, Viva-specific quota certification and optional source review flags. Candidate mapping must be approved first.</p><form method="post" action="{{ route('viva.board.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf<div class="col-md-6"><label class="form-label">Viva Board data file</label><input class="form-control" type="file" name="file" accept=".xlsx,.csv" required @disabled($counts['mapped']===0)></div><div class="col-auto"><button class="btn btn-primary" @disabled($counts['mapped']===0)>Upload &amp; Stage Board Data</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('viva.template.board') }}">Download Board Template</a></div></form></div></div>

    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-light">
            <div><h3 class="card-title mb-1">Processing Status Board</h3><div class="text-secondary small">A quick view of the Viva workflow · GMT+6 (Asia/Dhaka)</div></div>
        </div>
        <div class="card-body py-2">
            <div class="row g-3 align-items-center">
                <div class="col-md-6"><span class="text-secondary me-2">Current phase</span><span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($state->status) }}">{{ \App\Support\VivaStatusPresenter::label($state->status) }}</span></div>
                <div class="col-md-6 text-md-end"><span class="text-secondary me-2">Written result dependency</span>@if($writtenReady)<span class="badge bg-green-lt text-green">Ready</span>@else<span class="badge bg-yellow-lt text-yellow">Needs finalized Written result</span>@endif</div>
            </div>
        </div>
        <div class="table-responsive"><table class="table table-vcenter card-table mb-0"><tbody>
            <tr>
                <td class="fw-medium">Viva Candidate Mapping</td>
                <td>@if($latestMappingBatch)<span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($latestMappingBatch->status) }}">{{ \App\Support\VivaStatusPresenter::label($latestMappingBatch->status) }}</span>@else<span class="badge bg-secondary-lt text-secondary">Not started</span>@endif</td>
                <td class="text-secondary">Validates <code>user + reg + code</code> against the finalized Written-qualified population.</td>
            </tr>
            <tr>
                <td class="fw-medium">Viva Board Data</td>
                <td>@if($latestBoardBatch)<span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($latestBoardBatch->status) }}">{{ \App\Support\VivaStatusPresenter::label($latestBoardBatch->status) }}</span>@else<span class="badge bg-secondary-lt text-secondary">Pending</span>@endif</td>
                <td class="text-secondary">Board date, member, mark/ABS, Viva quota markings and review flags.</td>
            </tr>
            <tr><td class="fw-medium">Reconciliation &amp; Review</td><td><span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($state->reconciliation_generated_at ? 'completed' : 'pending') }}">{{ $state->reconciliation_generated_at ? 'Ready' : 'Pending' }}</span></td><td class="text-secondary">Eligible, mapped, appeared/absent and review-warning reconciliation.</td></tr>
            <tr><td class="fw-medium">Viva Result Processing</td><td><span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($state->result_processed_at ? 'completed' : 'pending') }}">{{ $state->result_processed_at ? 'Completed' : 'Pending' }}</span></td><td class="text-secondary">Config-driven Viva PASS/FAIL for ACTIVE candidates only.</td></tr>
            <tr><td class="fw-medium">Final Viva Review</td><td><span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($state->result_finalized_at ? 'completed' : 'pending') }}">{{ $state->result_finalized_at ? 'Finalized' : 'Pending' }}</span></td><td class="text-secondary">Confidential administrative finalization; no public Viva-mark publishing output.</td></tr>
        </tbody></table></div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><div><h3 class="card-title mb-1">Step 1 · Candidate Mapping</h3><div class="text-secondary small">The import engine will be enabled in V2.</div></div></div>
                <div class="card-body">
                    <p>Authoritative columns:</p>
                    <div class="mb-3"><code>user</code> · <code>reg</code> · <code>code</code></div>
                    <p class="text-secondary mb-3">The code is stored as text so leading zeroes are preserved. A duplicate code or a candidate outside the finalized Written-qualified result will be invalid.</p>
                    <a href="{{ route('viva.template.mapping') }}" class="btn btn-outline-primary">Download Mapping Template</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><div><h3 class="card-title mb-1">Step 2 · Board Data</h3><div class="text-secondary small">Board import is enabled after candidate mapping has been approved.</div></div></div>
                <div class="card-body">
                    <p>Required: <code>viva_date</code>, <code>member_id</code>, <code>code</code>, <code>mark</code></p>
                    <p>Optional: <code>viva_cff</code>, <code>viva_em</code>, <code>viva_phc</code>, <code>invalid</code>, <code>issue</code></p>
                    <p class="text-secondary mb-3"><code>mark</code> accepts a numeric value or <code>ABS</code>. Source review flags and quota mismatches create review warnings; they do not automatically exclude an ACTIVE candidate.</p>
                    <a href="{{ route('viva.template.board') }}" class="btn btn-outline-primary">Download Board Data Template</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><div><h3 class="card-title mb-1">Viva Rule Configuration</h3><div class="text-secondary small">Source: <code>config/viva.php</code></div></div></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><tbody>
            <tr><td>Viva full mark</td><td>{{ number_format($ruleSummary['full_mark'], 2) }}</td></tr>
            <tr><td>Viva pass rule</td><td>{{ number_format($ruleSummary['pass_percent'], 2) }}% · required mark {{ number_format($ruleSummary['pass_mark'], 2) }}</td></tr>
            <tr><td>High-mark review</td><td>{{ number_format($ruleSummary['high_mark_percent'], 2) }}% and above · {{ number_format($ruleSummary['high_mark_mark'], 2) }} marks at the current full mark</td></tr>
            <tr><td>Operational processing</td><td>Only ACTIVE candidates are processed. CANCELLED, WITHHELD and EXPELLED are excluded.</td></tr>
            <tr><td>Confidentiality</td><td>Viva mark and individual PASS/FAIL are internal administrative data; no public TXT/DOCX result publishing.</td></tr>
        </tbody></table></div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Recent Viva Import Activity</h3></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Batch</th><th>Type</th><th>File</th><th>Status</th><th>Rows</th><th>Warnings</th><th>Invalid</th><th>Approved</th></tr></thead><tbody>
            @forelse($recentBatches as $batch)
                <tr><td>#{{ $batch->id }}</td><td>{{ $batch->import_type === 'mapping' ? 'Candidate mapping' : 'Board data' }}</td><td>{{ $batch->original_name }}</td><td><span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($batch->status) }}">{{ \App\Support\VivaStatusPresenter::label($batch->status) }}</span></td><td>{{ number_format($batch->total_rows) }}</td><td>{{ number_format($batch->warning_rows) }}</td><td>{{ number_format($batch->invalid_rows) }}</td><td>{{ number_format($batch->approved_rows) }}</td></tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">No Viva import has been started yet.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Recent Viva Audit</h3></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Action</th><th>Actor</th><th>Reason</th><th>Time</th></tr></thead><tbody>
            @forelse($audits as $audit)
                <tr><td>{{ $audit->action }}</td><td>{{ $audit->actor_name ?? $audit->actor_id }}</td><td>{{ $audit->reason ?? '—' }}</td><td>{{ $audit->created_at?->format('d-m-Y h:i A') }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-secondary py-4">No Viva audit entries yet.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
</div></div>
@endsection
