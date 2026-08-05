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


    @if($state->result_processed_at)
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Viva PASS</div><div class="h2 mb-0">{{ number_format($counts['viva_pass']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Viva FAIL</div><div class="h2 mb-0">{{ number_format($counts['viva_fail']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Viva ABSENT</div><div class="h2 mb-0">{{ number_format($counts['viva_absent']) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Processing Version</div><div class="h2 mb-0">{{ $latestProcessingRun?->processing_version ?? '—' }}</div></div></div></div>
    </div>
    @endif

    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-light">
            <div>
                <h3 class="card-title mb-1">Processing Status Board</h3>
                <div class="text-secondary small">A quick view of where Viva processing currently stands · GMT+6 (Asia/Dhaka)</div>
            </div>
        </div>

        @if($latestReconciliationRun && in_array($latestReconciliationRun->status, ['queued', 'running'], true))
            <div
                class="card-body border-bottom"
                id="viva-reconciliation-progress"
                data-status-url="{{ route('viva.reconciliation.status', $latestReconciliationRun) }}"
            >
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="fw-semibold">Reconciliation in progress</div>
                        <div class="text-secondary small" id="viva-recon-progress-count">
                            {{ number_format($latestReconciliationRun->processed_candidates) }}
                            of {{ number_format($latestReconciliationRun->total_candidates) }}
                            Board records reviewed
                        </div>
                    </div>
                    <span class="badge bg-blue-lt text-blue" id="viva-recon-progress-text">
                        {{ number_format((float) $latestReconciliationRun->progress_percent, 1) }}%
                    </span>
                </div>
                <div class="progress">
                    <div
                        id="viva-recon-progress-bar"
                        class="progress-bar progress-bar-striped progress-bar-animated"
                        style="width: {{ (float) $latestReconciliationRun->progress_percent }}%"
                    ></div>
                </div>
            </div>
        @endif

        <div class="card-body py-2">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <span class="text-secondary me-2">Current phase</span>
                    @if($state->is_stale)
                        <span class="badge bg-yellow-lt text-yellow">Outdated — regeneration required</span>
                    @else
                        <span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($state->status) }}">
                            {{ \App\Support\VivaStatusPresenter::label($state->status) }}
                        </span>
                    @endif
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-secondary me-2">Written result dependency</span>
                    @if($writtenReady)
                        <span class="badge bg-green-lt text-green">Ready</span>
                    @else
                        <span class="badge bg-yellow-lt text-yellow">Needs finalized Written result</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table mb-0">
                <tbody>
                    <tr>
                        <td class="fw-medium">Viva Candidate Mapping</td>
                        <td>
                            @if($latestMappingBatch)
                                <span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($latestMappingBatch->status) }}">
                                    {{ \App\Support\VivaStatusPresenter::label($latestMappingBatch->status) }}
                                </span>
                            @else
                                <span class="badge bg-secondary-lt text-secondary">Not started</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                @if($latestMappingBatch)
                                    <a
                                        href="{{ route('viva.mapping.result', $latestMappingBatch) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Open Batch
                                    </a>
                                @elseif($writtenReady)
                                    <a href="#viva-mapping-import" class="btn btn-sm btn-primary">Start Mapping Import</a>
                                @else
                                    <span class="text-secondary">Finalize Written result first.</span>
                                @endif

                                <a href="{{ route('viva.template.mapping') }}" class="btn btn-sm btn-outline-secondary">
                                    Template
                                </a>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="fw-medium">Viva Board Data</td>
                        <td>
                            @if($latestBoardBatch)
                                <span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($latestBoardBatch->status) }}">
                                    {{ \App\Support\VivaStatusPresenter::label($latestBoardBatch->status) }}
                                </span>
                            @else
                                <span class="badge bg-secondary-lt text-secondary">Pending</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                @if($latestBoardBatch)
                                    <a
                                        href="{{ route('viva.board.result', $latestBoardBatch) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Open Batch
                                    </a>
                                @elseif($counts['mapped'] > 0)
                                    <a href="#viva-board-import" class="btn btn-sm btn-primary">Start Board Import</a>
                                @else
                                    <span class="text-secondary">Approve candidate mapping first.</span>
                                @endif

                                @if($counts['results'] > 0)
                                    <a href="{{ route('viva.candidates.index') }}" class="btn btn-sm btn-outline-primary">
                                        Browse / Edit
                                    </a>
                                @endif

                                <a href="{{ route('viva.template.board') }}" class="btn btn-sm btn-outline-secondary">
                                    Template
                                </a>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="fw-medium">Reconciliation &amp; Review</td>
                        <td>
                            @if($state->is_stale)
                                <span class="badge bg-yellow-lt text-yellow">Outdated</span>
                            @elseif($latestReconciliationRun && in_array($latestReconciliationRun->status, ['queued', 'running'], true))
                                <span class="badge {{ \App\Support\VivaStatusPresenter::badgeClass($latestReconciliationRun->status) }}">
                                    {{ \App\Support\VivaStatusPresenter::label($latestReconciliationRun->status) }}
                                </span>
                            @elseif($latestReconciliationRun && $latestReconciliationRun->status === 'failed')
                                <span class="badge bg-red-lt text-red">Needs attention</span>
                            @elseif($state->reconciliation_generated_at)
                                <span class="badge bg-green-lt text-green">Ready</span>
                            @else
                                <span class="badge bg-secondary-lt text-secondary">Pending</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                @if($state->is_stale)
                                    <form method="post" action="{{ route('viva.reconciliation.generate') }}">
                                        @csrf
                                        <button class="btn btn-sm btn-warning">Regenerate Reconciliation</button>
                                    </form>
                                    <a href="{{ route('viva.candidates.index') }}" class="btn btn-sm btn-outline-primary">Review Viva Data</a>
                                @elseif($latestReconciliationRun && in_array($latestReconciliationRun->status, ['queued', 'running'], true))
                                    <a
                                        href="{{ route('viva.reconciliation.show', $latestReconciliationRun) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        View Progress
                                    </a>
                                @elseif($state->reconciliation_generated_at && $latestReconciliationRun)
                                    <a
                                        href="{{ route('viva.reconciliation.show', $latestReconciliationRun) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        View Reconciliation
                                    </a>

                                    <a href="{{ route('viva.reviews') }}" class="btn btn-sm btn-outline-warning">
                                        Review Warnings
                                    </a>

                                    <form method="post" action="{{ route('viva.reconciliation.generate') }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">Regenerate</button>
                                    </form>
                                @elseif($counts['results'] > 0 && $writtenReady)
                                    <form method="post" action="{{ route('viva.reconciliation.generate') }}">
                                        @csrf
                                        <button class="btn btn-sm btn-primary">Generate Reconciliation</button>
                                    </form>
                                @else
                                    <span class="text-secondary">Approve Viva Board data first.</span>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="fw-medium">Viva Result Processing</td>
                        <td>
                            @if($latestProcessingRun && in_array($latestProcessingRun->status, ['queued','running'], true))
                                <span class="badge bg-blue-lt text-blue">Running</span>
                            @elseif($state->is_stale && $state->result_processed_at)
                                <span class="badge bg-yellow-lt text-yellow">Outdated</span>
                            @elseif($state->result_processed_at)
                                <span class="badge bg-green-lt text-green">Completed</span>
                            @elseif($state->reconciliation_generated_at && !$state->is_stale)
                                <span class="badge bg-teal-lt text-teal">Ready for processing</span>
                            @else
                                <span class="badge bg-secondary-lt text-secondary">Pending</span>
                            @endif
                        </td>
                        <td><div class="d-flex gap-2 flex-wrap">
                            @if($latestProcessingRun && in_array($latestProcessingRun->status, ['queued','running'], true))
                                <a href="{{ route('viva.processing.show',$latestProcessingRun) }}" class="btn btn-sm btn-outline-primary">View Progress</a>
                            @elseif($state->reconciliation_generated_at && !$state->is_stale)
                                <form method="post" action="{{ route('viva.processing.start') }}">@csrf<button class="btn btn-sm btn-primary">{{ $state->result_processed_at ? 'Process Viva Result Again' : 'Process Viva Result' }}</button></form>
                                @if($state->result_processed_at)<a href="{{ route('viva.results.index') }}" class="btn btn-sm btn-outline-primary">View Results</a>@endif
                            @elseif($state->is_stale)
                                <span class="text-secondary">Regenerate reconciliation before processing.</span>
                            @else
                                <span class="text-secondary">Generate reconciliation first.</span>
                            @endif
                        </div></td>
                    </tr>

                    <tr>
                        <td class="fw-medium">Final Viva Review</td>
                        <td>
                            @if($state->is_stale && $state->result_processed_at)<span class="badge bg-yellow-lt text-yellow">Outdated</span>
                            @elseif($state->result_finalized_at)<span class="badge bg-green-lt text-green">Finalized</span>
                            @elseif($state->result_processed_at)<span class="badge bg-teal-lt text-teal">Ready for final review</span>
                            @else<span class="badge bg-secondary-lt text-secondary">Pending</span>@endif
                        </td>
                        <td>
                            @if($state->result_processed_at)
                                <a href="{{ route('viva.final-review') }}" class="btn btn-sm {{ $state->result_finalized_at && !$state->is_stale ? 'btn-outline-success' : 'btn-success' }}">{{ $state->result_finalized_at && !$state->is_stale ? 'View Finalized Checkpoint' : 'Open Final Review' }}</a>
                            @else<span class="text-secondary">Complete Viva result processing first.</span>@endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3" id="viva-mapping-import"><div class="card-header"><h3 class="card-title">Import Viva Candidate Mapping</h3></div><div class="card-body"><p class="text-secondary">Upload <code>user, reg, code</code>. Each candidate must already be in the current finalized Written-qualified result, and every Viva code must be unique.</p><form method="post" action="{{ route('viva.mapping.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf<div class="col-md-6"><label class="form-label">Candidate mapping file</label><input class="form-control" type="file" name="file" accept=".xlsx,.csv" required @disabled(!$writtenReady)></div><div class="col-auto"><button class="btn btn-primary" @disabled(!$writtenReady)>Upload &amp; Stage Mapping</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('viva.template.mapping') }}">Download Mapping Template</a></div></form></div></div>

    <div class="card mb-3" id="viva-board-import"><div class="card-header"><h3 class="card-title">Import Viva Board Data</h3></div><div class="card-body"><p class="text-secondary">Upload board date, member, Viva code, mark/ABS, Viva-specific quota certification and optional source review flags. Candidate mapping must be approved first.</p><form method="post" action="{{ route('viva.board.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf<div class="col-md-6"><label class="form-label">Viva Board data file</label><input class="form-control" type="file" name="file" accept=".xlsx,.csv" required @disabled($counts['mapped']===0)></div><div class="col-auto"><button class="btn btn-primary" @disabled($counts['mapped']===0)>Upload &amp; Stage Board Data</button></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('viva.template.board') }}">Download Board Template</a></div></form></div></div>

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
        <div class="card-header">
            <div>
                <h3 class="card-title mb-1">Reconciliation &amp; Review</h3>
                <div class="text-secondary small">Compare finalized Written eligibility, Viva mappings and Board data; then refresh quota mismatch, source-review and high-mark flags.</div>
            </div>
            <div class="card-actions d-flex gap-2 flex-wrap">
                @if($latestReconciliationRun && $latestReconciliationRun->status === 'completed')
                    <a href="{{ route('viva.reconciliation.show', $latestReconciliationRun) }}" class="btn btn-outline-primary">View Reconciliation</a>
                    <a href="{{ route('viva.reviews') }}" class="btn btn-outline-warning">Review Warnings</a>
                @endif
                <form method="post" action="{{ route('viva.reconciliation.generate') }}">
                    @csrf
                    <button class="btn btn-primary" @disabled($counts['results'] === 0 || !$writtenReady || ($latestReconciliationRun && in_array($latestReconciliationRun->status, ['queued','running'], true)))>
                        {{ $latestReconciliationRun && $latestReconciliationRun->status === 'completed' ? 'Regenerate Reconciliation' : 'Generate Reconciliation' }}
                    </button>
                </form>
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

@if($latestReconciliationRun && in_array($latestReconciliationRun->status, ['queued','running'], true))
<script>
(() => {
    const panel = document.getElementById('viva-reconciliation-progress');
    if (!panel) return;
    const poll = async () => {
        try {
            const response = await fetch(panel.dataset.statusUrl, {headers: {'Accept':'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            const pct = Number(data.progress_percent || 0);
            document.getElementById('viva-recon-progress-bar').style.width = pct + '%';
            document.getElementById('viva-recon-progress-text').textContent = pct.toFixed(1) + '%';
            document.getElementById('viva-recon-progress-count').textContent = Number(data.processed_candidates).toLocaleString() + ' of ' + Number(data.total_candidates).toLocaleString() + ' Board records reviewed';
            if (data.finished) {
                window.location.reload();
                return;
            }
        } catch (e) {}
        setTimeout(poll, 2500);
    };
    setTimeout(poll, 1500);
})();
</script>
@endif


    @if($state->result_processed_at)
    <div class="card mb-3"><div class="card-header"><div><h3 class="card-title">Reports &amp; Exports</h3><div class="text-secondary small">Central shortcuts for confidential internal Viva verification reports.</div></div></div>
    <div class="card-body"><div class="d-flex flex-wrap gap-2">
    <a href="{{ route('viva.results.index') }}" class="btn btn-outline-primary">Confidential Result Listing</a>
    <a href="{{ route('viva.final-review') }}" class="btn btn-outline-success">Final Review</a>
    <a href="{{ route('viva.results.export',['scope'=>'pass']) }}" class="btn btn-outline-primary">PASS XLSX</a>
    <a href="{{ route('viva.results.export',['scope'=>'fail']) }}" class="btn btn-outline-primary">FAIL XLSX</a>
    <a href="{{ route('viva.results.export',['scope'=>'absent']) }}" class="btn btn-outline-primary">ABS XLSX</a>
    <a href="{{ route('viva.results.export',['scope'=>'warning']) }}" class="btn btn-outline-warning">Warning XLSX</a>
    <a href="{{ route('viva.reviews') }}" class="btn btn-outline-warning">Review Warnings</a>
    </div><div class="form-hint mt-3">Viva marks and individual PASS/FAIL remain confidential. Public TXT/DOCX publishing is intentionally unavailable.</div></div></div>
    @endif
@endsection
