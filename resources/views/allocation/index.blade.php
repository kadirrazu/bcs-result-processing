@extends('layouts.app')
@section('content')
<div class="page-header"><div class="container-xl"><h2 class="page-title">Allocation</h2><div class="text-secondary">Deterministic cadre allocation with strict upstream readiness, freeze and integrity gates.</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Action blocked.</strong><div>{{ $errors->first() }}</div></div>@endif
<div class="card mb-3"><div class="card-header"><div><h3 class="card-title">Upstream Readiness & Integrity Board</h3><div class="card-subtitle">Fast landing view uses finalized/stored integrity metadata. Full dataset hashes are re-verified by the strict server-side pre-run gate before Allocation can start.</div></div></div><div class="card-body"><div class="row row-cards">
@foreach($readiness['checks'] as $check)<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between gap-2"><strong>{{ $check['label'] }}</strong><span class="badge bg-{{ $check['ready']?'success':'danger' }}-lt">{{ $check['status'] }}</span></div><div class="small text-secondary mt-2">{{ $check['detail'] }}</div>@if(($check['hash_verified']??null)===true)<div class="small mt-2"><span class="badge bg-success-lt">HASH VERIFIED</span></div>@elseif(($check['stored_hash_present']??false)===true)<div class="small mt-2"><span class="badge bg-azure-lt">FINALIZED HASH ON FILE</span></div>@endif</div></div></div>@endforeach
</div><div class="mt-3"><strong>Pre-run Gate:</strong> <span class="badge bg-{{ $readiness['ready']?'success':'danger' }}-lt">{{ $readiness['ready']?'READY':'BLOCKED' }}</span> <span class="text-secondary small ms-2">Checked {{ $readiness['checked_at'] }} · {{ strtoupper(str_replace('_',' ', $readiness['verification_mode'] ?? 'strict')) }}</span></div></div></div>
@php
    $hasCurrentFreeze = $inputFreezes->contains(fn($f) => $f->status === 'frozen');
    $freezeBusy = in_array((string)$state->status, ['input_freeze_queued','input_freeze_running'], true);
    $freezeFailed = (string)$state->status === 'input_freeze_failed';
@endphp
<div class="card mb-3" id="allocation-input-freeze-card">
    <div class="card-header">
        <div>
            <h3 class="card-title">A2 — Frozen Allocation Input & Deterministic Queues</h3>
            <div class="card-subtitle">Strictly verify direct authoritative inputs, freeze an immutable fingerprint, then build choice-only merit queues. No allocation decision is made in this step.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <div class="small text-secondary">Freeze/Re-Freeze runs through the imports queue. The browser only polls processing status; refreshing or leaving this page does not stop the job.</div>
            </div>
            <div class="col-md-4 text-md-end">
                <form method="POST" action="{{ route('allocation.input-freeze.freeze') }}">
                    @csrf
                    <button class="btn btn-primary" {{ (($readiness['upstream_ready'] ?? false) && !$freezeBusy) ? '' : 'disabled' }}>
                        {{ $hasCurrentFreeze ? 'Re-Freeze Inputs & Rebuild Queues' : 'Freeze Direct Inputs & Build Queues' }}
                    </button>
                </form>
            </div>
        </div>

        <div id="input-freeze-progress-wrap" class="mt-4 {{ ($freezeBusy || $freezeFailed) ? '' : 'd-none' }}">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div><strong id="input-freeze-phase">{{ strtoupper(str_replace('_',' ', (string)($state->phase ?: $state->status))) }}</strong></div>
                <div class="text-secondary"><span id="input-freeze-percent">{{ (int)($state->progress_percent ?? 0) }}</span>%</div>
            </div>
            <div class="progress progress-sm mb-2">
                <div id="input-freeze-progress-bar" class="progress-bar" style="width: {{ (int)($state->progress_percent ?? 0) }}%"></div>
            </div>
            <div class="small text-secondary" id="input-freeze-message">{{ $state->progress_message ?: 'Waiting for queue worker…' }}</div>
            <div class="small text-secondary mt-1 d-none" id="input-freeze-counter"></div>
            <div class="alert alert-danger mt-3 mb-0 {{ $freezeFailed ? '' : 'd-none' }}" id="input-freeze-error">{{ $freezeFailed ? ($state->last_error ?: 'Allocation input freeze failed.') : '' }}</div>
        </div>
    </div>
    @if($inputFreezes->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-vcenter mb-0">
            <thead><tr><th>Version</th><th>Status</th><th>Choice Source</th><th>Candidates</th><th>Choice Ready</th><th>Queue Entries</th><th>Queue Hash</th><th></th></tr></thead>
            <tbody>
            @foreach($inputFreezes as $freeze)
                <tr>
                    <td>v{{ $freeze->version }}</td>
                    <td><span class="badge bg-{{ $freeze->status === 'frozen' ? 'success' : 'secondary' }}-lt">{{ strtoupper($freeze->status) }}</span></td>
                    <td><code>{{ strtoupper(str_replace('_',' ', $freeze->choice_source)) }}</code></td>
                    <td>{{ number_format($freeze->total_candidates) }}</td>
                    <td>{{ number_format($freeze->choice_ready_candidates) }}</td>
                    <td>{{ number_format($freeze->total_queue_entries) }}</td>
                    <td class="small text-break" style="max-width:220px"><code>{{ $freeze->queue_hash }}</code></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.input-freeze.show',$freeze) }}">View Frozen Input</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@php
    $phase1Busy = in_array((string)$state->status, ['phase1_queued','phase1_running'], true);
    $phase1Failed = (string)$state->status === 'phase1_failed';
    $currentFreeze = $inputFreezes->first(fn($f) => $f->status === 'frozen');
    $latestRun = $allocationRuns->first();
    $canStartPhase1 = ($readiness['ready'] ?? false) && $currentFreeze && !$phase1Busy && !$freezeBusy && !(bool)$state->is_stale;
@endphp
<div class="card mb-3" id="allocation-phase1-card">
    <div class="card-header">
        <div>
            <h3 class="card-title">A3 — Phase-1 MQ + Quota Allocation</h3>
            <div class="card-subtitle">Deterministic deferred allocation against frozen MQ/CFF/EM/PHC capacity. Phase-1 reaches a fixed point; quota-vacancy conversion and NM/shifting remain reserved for A4.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <div class="small text-secondary">The worker strictly verifies A2 before and after processing, checks deterministic replay hashes, validates hard seat/quota invariants, and only then commits the versioned Phase-1 result.</div>
                @if($currentFreeze)
                    <div class="small mt-2">Current frozen input: <strong>v{{ $currentFreeze->version }}</strong> · Queue entries {{ number_format($currentFreeze->total_queue_entries) }}</div>
                @endif
            </div>
            <div class="col-md-4 text-md-end">
                <form method="POST" action="{{ route('allocation.phase-one.start') }}">
                    @csrf
                    <button class="btn btn-primary" {{ $canStartPhase1 ? '' : 'disabled' }}>
                        {{ $latestRun && $latestRun->status === 'phase1_complete' ? 'Re-run Phase-1 MQ + Quota' : 'Start Phase-1 MQ + Quota' }}
                    </button>
                </form>
            </div>
        </div>

        <div id="phase1-progress-wrap" class="mt-4 {{ ($phase1Busy || $phase1Failed) ? '' : 'd-none' }}">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong id="phase1-phase">{{ strtoupper(str_replace('_',' ', (string)($state->phase ?: $state->status))) }}</strong>
                <span class="text-secondary"><span id="phase1-percent">{{ (int)($state->progress_percent ?? 0) }}</span>%</span>
            </div>
            <div class="progress progress-sm mb-2"><div id="phase1-progress-bar" class="progress-bar" style="width: {{ (int)($state->progress_percent ?? 0) }}%"></div></div>
            <div class="small text-secondary" id="phase1-message">{{ $state->progress_message ?: 'Waiting for queue worker…' }}</div>
            <div class="small text-secondary mt-1 d-none" id="phase1-counter"></div>
            <div class="alert alert-danger mt-3 mb-0 {{ $phase1Failed ? '' : 'd-none' }}" id="phase1-error">{{ $phase1Failed ? ($state->last_error ?: 'Allocation Phase-1 failed.') : '' }}</div>
        </div>
    </div>

    @if($allocationRuns->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-vcenter mb-0">
            <thead><tr><th>Run</th><th>Status</th><th>Allocated</th><th>Unallocated</th><th>MQ</th><th>CFF</th><th>EM</th><th>PHC</th><th>Iterations</th><th></th></tr></thead>
            <tbody>
            @foreach($allocationRuns as $run)
                <tr>
                    <td>v{{ $run->version }}</td>
                    <td><span class="badge bg-{{ $run->status === 'phase1_complete' ? 'success' : ($run->status === 'failed' ? 'danger' : 'azure') }}-lt">{{ strtoupper(str_replace('_',' ', $run->status)) }}</span></td>
                    <td>{{ number_format($run->allocated_count) }}</td>
                    <td>{{ number_format($run->unallocated_count) }}</td>
                    <td>{{ number_format($run->mq_count) }}</td>
                    <td>{{ number_format($run->cff_count) }}</td>
                    <td>{{ number_format($run->em_count) }}</td>
                    <td>{{ number_format($run->phc_count) }}</td>
                    <td>{{ number_format($run->iteration_count) }}</td>
                    <td class="text-end">@if($run->status === 'phase1_complete')<a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.runs.show',$run) }}">View Phase-1</a>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@php
    $latestCompletedA3 = $allocationRuns->first(fn($r) => $r->status === 'phase1_complete');
    $latestA4 = $a4Runs->first();
    $a4Busy = $latestA4 && in_array((string)$latestA4->status, ['queued','running'], true);
@endphp
<div class="card mb-3" id="allocation-a4-card">
    <div class="card-header">
        <div>
            <h3 class="card-title">A4 — NM + Shifting</h3>
            <div class="card-subtitle">Consumes an immutable completed A3 run. Vacant/released quota capacity becomes pure merit/NM capacity; A3 evidence remains unchanged.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                @if($latestCompletedA3)
                    <div>Source Phase-1: <strong>v{{ $latestCompletedA3->version }}</strong> · Allocated {{ number_format($latestCompletedA3->allocated_count) }} · Temporary {{ number_format($latestCompletedA3->temporary_count) }}</div>
                    <div class="small text-secondary mt-1">A4 can only improve TEMPORARY allocations or allocate previously unallocated candidates; FINAL A3 candidates stay locked.</div>
                @else
                    <div class="text-secondary">Complete A3 Phase-1 before starting A4.</div>
                @endif
            </div>
            <div class="col-md-4 text-md-end">
                @if($latestA4 && $latestA4->status === 'a4_complete')
                    <a class="btn btn-success" href="{{ route('allocation.a4.show', $latestA4) }}">View A4 Result</a>
                @elseif($a4Busy)
                    <a class="btn btn-warning" href="{{ route('allocation.a4.processing', $latestA4) }}">View A4 Processing</a>
                @elseif($latestCompletedA3)
                    <form method="POST" action="{{ route('allocation.a4.start', $latestCompletedA3) }}">@csrf
                        <button class="btn btn-primary" type="submit">{{ $latestA4 ? 'Re-run A4 NM + Shifting' : 'Start A4 NM + Shifting' }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    @if($latestA4)
    <div id="a4-landing-progress-wrap" class="card-body border-top {{ $a4Busy || $latestA4->status === 'failed' ? '' : 'd-none' }}" data-status-url="{{ route('allocation.a4.status', $latestA4) }}">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div><strong id="a4-landing-phase">{{ strtoupper(str_replace('_',' ', $latestA4->phase ?: $latestA4->status)) }}</strong><div class="small text-secondary" id="a4-landing-message">{{ $latestA4->progress_message }}</div></div>
            <span id="a4-landing-percent">{{ (int)$latestA4->progress_percent }}</span>%
        </div>
        <div class="progress"><div id="a4-landing-progress-bar" class="progress-bar" style="width: {{ (int)$latestA4->progress_percent }}%"></div></div>
        <div id="a4-landing-counter" class="small text-secondary mt-2 {{ (int)$latestA4->progress_total > 0 ? '' : 'd-none' }}">Processed {{ number_format($latestA4->progress_current) }} / {{ number_format($latestA4->progress_total) }}</div>
        <div id="a4-landing-error" class="alert alert-danger mt-3 mb-0 {{ $latestA4->status === 'failed' ? '' : 'd-none' }}">{{ $latestA4->failure_message }}</div>
    </div>
    @endif
    @if($a4Runs->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-vcenter mb-0">
            <thead><tr><th>Run</th><th>A3 Source</th><th>Status</th><th>Allocated</th><th>Unallocated</th><th>NM</th><th>SHIFTED</th><th>Quota→Merit</th><th>Iterations</th><th></th></tr></thead>
            <tbody>
            @foreach($a4Runs as $a4)
                <tr>
                    <td>v{{ $a4->version }}</td>
                    <td>A3 v{{ $a4->phase1Run?->version ?? '—' }}</td>
                    <td><span class="badge bg-{{ $a4->status === 'a4_complete' ? 'success' : ($a4->status === 'failed' ? 'danger' : 'azure') }}-lt">{{ strtoupper(str_replace('_',' ', $a4->status)) }}</span></td>
                    <td>{{ number_format($a4->allocated_count) }}</td>
                    <td>{{ number_format($a4->unallocated_count) }}</td>
                    <td>{{ number_format($a4->nm_count) }}</td>
                    <td>{{ number_format($a4->shifted_count) }}</td>
                    <td>{{ number_format($a4->quota_to_merit_count) }}</td>
                    <td>{{ number_format($a4->iteration_count) }}</td>
                    <td class="text-end">
                        @if($a4->status === 'a4_complete')
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.a4.show', $a4) }}">View A4</a>
                        @else
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('allocation.a4.processing', $a4) }}">View A4 Run</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
<div class="row row-cards mb-3"><div class="col-md-5"><div class="card h-100">
<div class="card-header"><div><h3 class="card-title">Allocation Settings</h3><div class="card-subtitle">Read-only here. Configure in <code>{{ $settingsInfo['config_file'] }}</code>.</div></div></div>
<div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3"><span>Configuration status</span><span class="badge bg-{{ $settingsInfo['matches_frozen'] ? 'success' : 'warning' }}-lt">{{ $settingsInfo['matches_frozen'] ? 'FROZEN & CURRENT' : 'REVIEW / FREEZE REQUIRED' }}</span></div>
<div class="mb-2">Quota priority: <code>{{ implode(' → ', $settingsInfo['current']['quota_priority']) }}</code></div>
<div class="mb-2"><strong>Quota Breakup Minimum Total Posts:</strong> {{ $settingsInfo['current']['quota_breakup_minimum_total_posts'] }} <span class="badge bg-secondary-lt ms-1">LOCKED RULE</span></div>
<div class="small text-secondary mb-3">Quota breakup applies only when sanctioned total posts are 10 or more. For 1–9 posts, all seats are MQ and CFF/EM/PHC are zero.</div>
<div>Provisional target: MQ {{ $settingsInfo['current']['mq_percent'] }}%, CFF {{ $settingsInfo['current']['cff_percent'] }}%, EM {{ $settingsInfo['current']['em_percent'] }}%, PHC {{ $settingsInfo['current']['phc_percent'] }}%</div>
<div class="alert alert-info mt-3 mb-0 py-2"><div class="fw-semibold">How to change</div><div class="small">Edit <code>config/allocation.php</code>, then run <code>php artisan config:clear</code> (or rebuild production config cache), review this card, and freeze the current config.</div></div>
@if($settingsInfo['frozen_hash'])<div class="small text-secondary text-break mt-3">Frozen hash: <code>{{ $settingsInfo['frozen_hash'] }}</code></div>@endif
<div class="small text-secondary text-break mt-1">Current config hash: <code>{{ $settingsInfo['current_hash'] }}</code></div>
</div>
<div class="card-footer"><form method="POST" action="{{ route('allocation.settings.finalize') }}">@csrf<button class="btn btn-primary">{{ $settingsInfo['matches_frozen'] ? 'Re-freeze Current Config' : 'Finalize / Freeze Current Config' }}</button></form></div>
</div></div>
<div class="col-md-7"><div class="card h-100"><div class="card-header"><h3 class="card-title">Seat Breakup</h3></div><div class="card-body"><div class="mb-2"><code>sl | cadre_code | total_post | mq | cff | em | phc</code></div><div class="small text-secondary">sl, cadre_code and total_post are copied from finalized Circular. For total_post 1-9, MQ must equal total_post and all other quotas must be zero.</div><form class="mt-3" method="POST" enctype="multipart/form-data" action="{{ route('allocation.seat-breakup.upload') }}">@csrf<div class="d-flex gap-2 flex-wrap"><input class="form-control" style="max-width:360px" type="file" name="file" accept=".xlsx,.xls" required><button class="btn btn-primary">Validate Upload</button><a class="btn btn-outline-secondary" href="{{ route('allocation.seat-breakup.template') }}">Generate Excel</a></div></form></div></div></div></div>
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Seat Breakup Versions</h3></div><div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th>Version</th><th>Status</th><th>Rows</th><th>Total</th><th>MQ</th><th>CFF</th><th>EM</th><th>PHC</th><th>Hash</th><th></th></tr></thead><tbody>@forelse($seatVersions as $v)<tr><td>v{{ $v->version }}</td><td><span class="badge bg-{{ $v->status==='finalized'?'success':($v->status==='validated'?'azure':'secondary') }}-lt">{{ strtoupper($v->status) }}</span></td><td>{{ $v->total_rows }}</td><td>{{ $v->total_posts }}</td><td>{{ $v->mq_posts }}</td><td>{{ $v->cff_posts }}</td><td>{{ $v->em_posts }}</td><td>{{ $v->phc_posts }}</td><td class="small text-break" style="max-width:220px"><code>{{ $v->dataset_hash ?: '—' }}</code></td><td><div class="btn-list flex-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.seat-breakup.show',$v) }}">View Data</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('allocation.seat-breakup.pdf',$v) }}">PDF</a>@if($v->status==='validated')<form method="POST" action="{{ route('allocation.seat-breakup.finalize',$v) }}">@csrf<button class="btn btn-sm btn-success">Finalize / Freeze</button></form>@endif</div></td></tr>@empty<tr><td colspan="10" class="text-center text-secondary py-4">No Seat Breakup uploaded yet.</td></tr>@endforelse</tbody></table></div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Processing State</h3></div><div class="card-body"><strong>{{ strtoupper($state->status) }}</strong>@if($state->is_stale)<div class="alert alert-warning mt-2 mb-0">STALE: {{ $state->stale_reason }}</div>@endif<div class="text-secondary small mt-2">A3 processes frozen MQ/CFF/EM/PHC capacity to a Phase-1 fixed point. Quota vacancy conversion and NM/shifting remain pending for A4.</div></div></div>
</div></div>
<script>
(function () {
    const busyStatuses = ['input_freeze_queued', 'input_freeze_running'];
    const initialBusy = @json($freezeBusy);
    if (!initialBusy) return;

    const url = @json(route('allocation.input-freeze.status'));
    const wrap = document.getElementById('input-freeze-progress-wrap');
    const bar = document.getElementById('input-freeze-progress-bar');
    const pct = document.getElementById('input-freeze-percent');
    const phase = document.getElementById('input-freeze-phase');
    const message = document.getElementById('input-freeze-message');
    const counter = document.getElementById('input-freeze-counter');
    const error = document.getElementById('input-freeze-error');

    async function poll() {
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) throw new Error('Unable to read Allocation input freeze status.');
            const data = await response.json();
            const percent = Number(data.progress_percent || 0);
            wrap.classList.remove('d-none');
            bar.style.width = percent + '%';
            pct.textContent = percent;
            phase.textContent = String(data.phase || data.status || '').replaceAll('_', ' ').toUpperCase();
            message.textContent = data.message || '';

            if (Number(data.progress_total || 0) > 0) {
                counter.textContent = 'Processed ' + Number(data.progress_current || 0).toLocaleString() + ' / ' + Number(data.progress_total || 0).toLocaleString();
                counter.classList.remove('d-none');
            } else {
                counter.classList.add('d-none');
            }

            if (data.status === 'input_freeze_failed') {
                error.textContent = data.error || 'Allocation input freeze failed.';
                error.classList.remove('d-none');
                return;
            }

            if (!busyStatuses.includes(data.status)) {
                window.location.reload();
                return;
            }
            window.setTimeout(poll, 1200);
        } catch (e) {
            message.textContent = e.message + ' Retrying…';
            window.setTimeout(poll, 2500);
        }
    }
    window.setTimeout(poll, 500);
})();
</script>

<script>
(function () {
    const busyStatuses = ['phase1_queued', 'phase1_running'];
    const initialBusy = @json($phase1Busy);
    if (!initialBusy) return;

    const url = @json(route('allocation.phase-one.status'));
    const wrap = document.getElementById('phase1-progress-wrap');
    const bar = document.getElementById('phase1-progress-bar');
    const pct = document.getElementById('phase1-percent');
    const phase = document.getElementById('phase1-phase');
    const message = document.getElementById('phase1-message');
    const counter = document.getElementById('phase1-counter');
    const error = document.getElementById('phase1-error');

    async function pollPhaseOne() {
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) throw new Error('Unable to read Allocation Phase-1 status.');
            const data = await response.json();
            const percent = Number(data.progress_percent || 0);
            wrap.classList.remove('d-none');
            bar.style.width = percent + '%';
            pct.textContent = percent;
            phase.textContent = String(data.phase || data.status || '').replaceAll('_', ' ').toUpperCase();
            message.textContent = data.message || '';

            if (Number(data.progress_total || 0) > 0) {
                counter.textContent = 'Resolved ' + Number(data.progress_current || 0).toLocaleString() + ' / ' + Number(data.progress_total || 0).toLocaleString();
                counter.classList.remove('d-none');
            } else {
                counter.classList.add('d-none');
            }

            if (data.status === 'phase1_failed') {
                error.textContent = data.error || 'Allocation Phase-1 failed.';
                error.classList.remove('d-none');
                return;
            }
            if (!busyStatuses.includes(data.status)) {
                window.location.reload();
                return;
            }
            window.setTimeout(pollPhaseOne, 1200);
        } catch (e) {
            message.textContent = e.message + ' Retrying…';
            window.setTimeout(pollPhaseOne, 2500);
        }
    }
    window.setTimeout(pollPhaseOne, 500);
})();
</script>

<script>
(function () {
    const busyStatuses = ['queued', 'running'];
    const initialBusy = @json($a4Busy);
    if (!initialBusy) return;

    const wrap = document.getElementById('a4-landing-progress-wrap');
    if (!wrap) return;
    const url = wrap.dataset.statusUrl;
    const bar = document.getElementById('a4-landing-progress-bar');
    const pct = document.getElementById('a4-landing-percent');
    const phase = document.getElementById('a4-landing-phase');
    const message = document.getElementById('a4-landing-message');
    const counter = document.getElementById('a4-landing-counter');
    const error = document.getElementById('a4-landing-error');

    async function pollA4() {
        try {
            const response = await fetch(url, {headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (!response.ok) throw new Error('Unable to read Allocation A4 status.');
            const data = await response.json();
            const percent = Number(data.progress_percent || 0);
            wrap.classList.remove('d-none');
            bar.style.width = percent + '%';
            pct.textContent = percent;
            phase.textContent = String(data.phase || data.status || '').replaceAll('_',' ').toUpperCase();
            message.textContent = data.message || '';
            if (Number(data.progress_total || 0) > 0) {
                counter.textContent = 'Processed ' + Number(data.progress_current || 0).toLocaleString() + ' / ' + Number(data.progress_total || 0).toLocaleString();
                counter.classList.remove('d-none');
            } else counter.classList.add('d-none');

            if (data.status === 'failed') {
                error.textContent = data.error || 'Allocation A4 failed. No A4 result was committed.';
                error.classList.remove('d-none');
                return;
            }
            if (!busyStatuses.includes(data.status)) { window.location.reload(); return; }
            window.setTimeout(pollA4, 1200);
        } catch (e) {
            message.textContent = e.message + ' Retrying…';
            window.setTimeout(pollA4, 2500);
        }
    }
    window.setTimeout(pollA4, 500);
})();
</script>

@endsection
