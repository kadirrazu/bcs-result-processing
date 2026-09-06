@extends('layouts.app')
@section('content')
<style>
/* Landing-only alignment helpers: preserve Status/Hash/Action readability while centering numeric/value columns. */
#allocation-seat-breakup-card tbody td:nth-child(1),
#allocation-seat-breakup-card tbody td:nth-child(3),
#allocation-seat-breakup-card tbody td:nth-child(4),
#allocation-seat-breakup-card tbody td:nth-child(5),
#allocation-seat-breakup-card tbody td:nth-child(6),
#allocation-seat-breakup-card tbody td:nth-child(7),
#allocation-seat-breakup-card tbody td:nth-child(8) { text-align:center; vertical-align:middle; }

#allocation-input-freeze-card tbody td:nth-child(1),
#allocation-input-freeze-card tbody td:nth-child(4),
#allocation-input-freeze-card tbody td:nth-child(5),
#allocation-input-freeze-card tbody td:nth-child(6) { text-align:center; vertical-align:middle; }

#allocation-phase1-card tbody td:nth-child(1),
#allocation-phase1-card tbody td:nth-child(3),
#allocation-phase1-card tbody td:nth-child(4),
#allocation-phase1-card tbody td:nth-child(5),
#allocation-phase1-card tbody td:nth-child(6),
#allocation-phase1-card tbody td:nth-child(7),
#allocation-phase1-card tbody td:nth-child(8),
#allocation-phase1-card tbody td:nth-child(9),
#allocation-a4-card tbody td:nth-child(1),
#allocation-a4-card tbody td:nth-child(2),
#allocation-a4-card tbody td:nth-child(4),
#allocation-a4-card tbody td:nth-child(5),
#allocation-a4-card tbody td:nth-child(6),
#allocation-a4-card tbody td:nth-child(7),
#allocation-a4-card tbody td:nth-child(8),
#allocation-a4-card tbody td:nth-child(9) { text-align:center; vertical-align:middle; }

.allocation-setting-line { display:flex; align-items:center; gap:.5rem; margin-bottom:.5rem; }
.allocation-setting-label { font-weight:700; color:var(--tblr-body-color); min-width:215px; }
.allocation-setting-value { color:var(--tblr-primary); font-weight:600; }
.allocation-stage-summary .summary-row { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:.6rem 0; border-bottom:1px solid var(--tblr-border-color); }
.allocation-stage-summary .summary-row:last-child { border-bottom:0; }
.allocation-stage-summary .summary-name { font-weight:700; min-width:190px; }
.allocation-stage-summary .summary-detail { flex:1; color:var(--tblr-secondary); }
.allocation-stage-summary .summary-status { min-width:155px; text-align:right; }
</style>
<div class="page-header"><div class="container-xl"><h2 class="page-title">Allocation</h2><div class="text-secondary">Deterministic cadre allocation with strict upstream readiness, freeze and integrity gates.</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Action blocked.</strong><div>{{ $errors->first() }}</div></div>@endif
<div class="card mb-3"><div class="card-header"><div><h3 class="card-title">Upstream Readiness & Integrity Board</h3><div class="card-subtitle">Fast landing view uses finalized/stored integrity metadata. Full dataset hashes are re-verified by the strict server-side pre-run gate before Allocation can start.</div></div></div><div class="card-body"><div class="row row-cards">
@foreach($readiness['checks'] as $check)<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between gap-2"><strong>{{ $check['label'] }}</strong><span class="badge bg-{{ $check['ready']?'success':'danger' }}-lt">{{ $check['status'] }}</span></div><div class="small text-secondary mt-2">{{ $check['detail'] }}</div>@if(($check['hash_verified']??null)===true)<div class="small mt-2"><span class="badge bg-success-lt">HASH VERIFIED</span></div>@elseif(($check['stored_hash_present']??false)===true)<div class="small mt-2"><span class="badge bg-azure-lt">FINALIZED HASH ON FILE</span></div>@endif</div></div></div>@endforeach
</div><div class="mt-3"><strong>Pre-run Gate:</strong> <span class="badge bg-{{ $readiness['ready']?'success':'danger' }}-lt">{{ $readiness['ready']?'READY':'BLOCKED' }}</span> <span class="text-secondary small ms-2">Checked {{ $readiness['checked_at'] }} · {{ strtoupper(str_replace('_',' ', $readiness['verification_mode'] ?? 'strict')) }}</span></div></div></div>

@php
    /* Operator-facing effective stage summary. Historical/stored rows remain below in each section. */
    $summarySeat = $seatVersions->first(fn($v) => $v->status === 'finalized') ?: $seatVersions->first();
    $summaryFreeze = $inputFreezes->first(fn($f) => $f->status === 'frozen') ?: $inputFreezes->first();
    $summaryA3 = $allocationRuns->first();
    $summaryA4 = $a4Runs->first();
    $summaryA5 = $a5Runs->first();

    $seatSummaryStatus = $summarySeat?->status === 'finalized' ? 'FINALIZED / CURRENT' : ($summarySeat ? strtoupper((string)$summarySeat->status) : 'NOT READY');
    $seatSummaryClass = $summarySeat?->status === 'finalized' ? 'success' : ($summarySeat ? 'warning' : 'secondary');
    $seatSummaryDetail = $summarySeat?->status === 'finalized'
        ? 'Seat Breakup v'.$summarySeat->version.' is the current finalized capacity authority.'
        : ($summarySeat ? 'Seat Breakup v'.$summarySeat->version.' requires finalization before A2.' : 'Generate/upload and finalize the Circular-authoritative Seat Breakup.');

    $a1SummaryReady = (bool)($settingsInfo['matches_frozen'] ?? false);
    $a1SummaryStatus = $a1SummaryReady ? 'FROZEN & CURRENT' : 'FREEZE REQUIRED';
    $a1SummaryClass = $a1SummaryReady ? 'success' : 'warning';
    $a1SummaryDetail = $a1SummaryReady ? 'Current config hash matches the frozen A1 configuration.' : 'Review config/allocation.php and freeze the current A1 configuration.';

    $a2BusySummary = in_array((string)$state->status, ['input_freeze_queued','input_freeze_running'], true);
    $a2SummaryStatus = $a2BusySummary ? 'PROCESSING' : ($summaryFreeze?->status === 'frozen' && !(bool)$state->is_stale ? 'FROZEN / CURRENT' : ((bool)$state->is_stale ? 'STALE / RE-FREEZE' : ($summaryFreeze ? strtoupper((string)$summaryFreeze->status) : 'NOT STARTED')));
    $a2SummaryClass = $a2BusySummary ? 'azure' : ($summaryFreeze?->status === 'frozen' && !(bool)$state->is_stale ? 'success' : ((bool)$state->is_stale ? 'warning' : 'secondary'));
    $a2SummaryDetail = $a2BusySummary ? ($state->progress_message ?: 'Frozen input and deterministic queues are being rebuilt.') : ((bool)$state->is_stale ? ($state->stale_reason ?: 'Allocation inputs changed; re-freeze A2.') : ($summaryFreeze ? 'Frozen input v'.$summaryFreeze->version.' · '.number_format($summaryFreeze->total_queue_entries).' deterministic queue entries.' : 'Freeze authoritative inputs and build deterministic queues.'));

    $a3SummaryStale = $summaryA3 && (bool)$summaryA3->is_stale;
    $a3SummaryStatus = !$summaryA3 ? 'NOT STARTED' : ($a3SummaryStale ? 'STALE / OUTDATED' : ($summaryA3->status === 'phase1_complete' ? 'PHASE-1 COMPLETE' : strtoupper(str_replace('_',' ', (string)$summaryA3->status))));
    $a3SummaryClass = !$summaryA3 ? 'secondary' : ($a3SummaryStale ? 'warning' : ($summaryA3->status === 'phase1_complete' ? 'success' : (str_contains((string)$summaryA3->status, 'failed') ? 'danger' : 'azure')));
    $a3SummaryDetail = !$summaryA3 ? 'Run A3 after A2 is current.' : ($a3SummaryStale ? ($summaryA3->stale_reason ?: 'A1/A2/Seat Breakup changed; re-run Phase-1.') : ($summaryA3->status === 'phase1_complete' ? 'A3 v'.$summaryA3->version.' is current · '.number_format($summaryA3->allocated_count).' allocated.' : ($state->progress_message ?: 'Phase-1 processing is in progress.')));

    $a4SummaryStale = $summaryA4 && (bool)$summaryA4->is_stale;
    $a4SummaryStatus = !$summaryA4 ? 'NOT STARTED' : ($a4SummaryStale ? 'STALE / OUTDATED' : ($summaryA4->status === 'a4_complete' ? 'PHASE-2 COMPLETE' : strtoupper(str_replace('_',' ', (string)$summaryA4->status))));
    $a4SummaryClass = !$summaryA4 ? 'secondary' : ($a4SummaryStale ? 'warning' : ($summaryA4->status === 'a4_complete' ? 'success' : ($summaryA4->status === 'failed' ? 'danger' : 'azure')));
    $a4SummaryDetail = !$summaryA4 ? 'Run A4 after a current, completed A3 exists.' : ($a4SummaryStale ? ($summaryA4->stale_reason ?: 'A3 or Allocation inputs changed; re-run Phase-2.') : ($summaryA4->status === 'a4_complete' ? 'A4 v'.$summaryA4->version.' is current · '.number_format($summaryA4->allocated_count).' final allocated results after NM/shifting.' : ($summaryA4->progress_message ?: 'Phase-2 processing is in progress.')));

    $a5SummaryStale = $summaryA5 && (bool)$summaryA5->is_stale;
    $a5SummaryStatus = !$summaryA5 ? 'NOT STARTED' : ($a5SummaryStale ? 'STALE / OUTDATED' : match((string)$summaryA5->status) {
        'finalized' => 'FINALIZED / REPORTING READY',
        'validated_ok' => '100% PASS / FINALIZE REQUIRED',
        'validated_failed' => 'VALIDATION FAILED / BLOCKED',
        'queued', 'running' => 'PROCESSING',
        'failed' => 'FAILED',
        default => strtoupper(str_replace('_',' ', (string)$summaryA5->status)),
    });
    $a5SummaryClass = !$summaryA5 ? 'secondary' : ($a5SummaryStale ? 'warning' : match((string)$summaryA5->status) {
        'finalized' => 'success', 'validated_ok' => 'azure', 'validated_failed', 'failed' => 'danger', 'queued', 'running' => 'azure', default => 'secondary'
    });
    $a5SummaryDetail = !$summaryA5
        ? 'Run A5 after a current completed A4 to validate final eligibility, quota entitlement and cadre seat limits.'
        : ($a5SummaryStale ? ($summaryA5->stale_reason ?: 'A4 or authoritative inputs changed; re-run A5.')
            : ((string)$summaryA5->status === 'finalized'
                ? 'A5 v'.$summaryA5->version.' is finalized · 100% candidate validity PASS and all cadre seat limits PASS.'
                : ($summaryA5->progress_message ?: 'Final Allocation Validity Check is awaiting action.')));
@endphp
<div class="card mb-3 allocation-stage-summary" id="allocation-processing-summary">
    <div class="card-header">
        <div>
            <h3 class="card-title">Allocation Processing Summary</h3>
            <div class="card-subtitle">Operator view of the current authoritative state for each Allocation preparation/processing stage and the next required action.</div>
        </div>
        <div class="ms-auto text-end"><span class="badge bg-{{ $readiness['ready'] ? 'success' : 'danger' }}-lt">PRE-RUN GATE {{ $readiness['ready'] ? 'READY' : 'BLOCKED' }}</span></div>
    </div>
    <div class="card-body py-2">
        <div class="summary-row"><div class="summary-name">Seat Breakup</div><div class="summary-detail">{{ $seatSummaryDetail }}</div><div class="summary-status"><span class="badge bg-{{ $seatSummaryClass }}-lt">{{ $seatSummaryStatus }}</span></div></div>
        <div class="summary-row"><div class="summary-name">A1 — Allocation Settings</div><div class="summary-detail">{{ $a1SummaryDetail }}</div><div class="summary-status"><span class="badge bg-{{ $a1SummaryClass }}-lt">{{ $a1SummaryStatus }}</span></div></div>
        <div class="summary-row"><div class="summary-name">A2 — Frozen Input</div><div class="summary-detail">{{ $a2SummaryDetail }}</div><div class="summary-status"><span class="badge bg-{{ $a2SummaryClass }}-lt">{{ $a2SummaryStatus }}</span></div></div>
        <div class="summary-row"><div class="summary-name">A3 — Phase-1</div><div class="summary-detail">{{ $a3SummaryDetail }}</div><div class="summary-status"><span class="badge bg-{{ $a3SummaryClass }}-lt">{{ $a3SummaryStatus }}</span></div></div>
        <div class="summary-row"><div class="summary-name">A4 — Phase-2</div><div class="summary-detail">{{ $a4SummaryDetail }}</div><div class="summary-status"><span class="badge bg-{{ $a4SummaryClass }}-lt">{{ $a4SummaryStatus }}</span></div></div>
        <div class="summary-row"><div class="summary-name">A5 — Final Validity Check</div><div class="summary-detail">{{ $a5SummaryDetail }}</div><div class="summary-status"><span class="badge bg-{{ $a5SummaryClass }}-lt">{{ $a5SummaryStatus }}</span></div></div>
    </div>
</div>
<div class="card mb-3" id="allocation-seat-breakup-card">
    <div class="card-header"><div><h3 class="card-title">Seat Breakup</h3><div class="card-subtitle">Initial Allocation preparation: generate, edit and validate the Circular-authoritative MQ/CFF/EM/PHC breakup before freezing A1/A2 inputs.</div></div></div>
    <div class="card-body">
        <div class="mb-2"><code>sl | cadre_code | total_post | mq | cff | em | phc</code></div>
        <div class="small text-secondary">sl, cadre_code and total_post are copied from finalized Circular. For total_post 1-9, MQ must equal total_post and all other quotas must be zero.</div>
        <form class="mt-3" method="POST" enctype="multipart/form-data" action="{{ route('allocation.seat-breakup.upload') }}">@csrf
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <input class="form-control" style="max-width:440px" type="file" name="file" accept=".xlsx,.xls" required>
                <button class="btn btn-primary">Validate Upload</button>
                <a class="btn btn-outline-secondary" href="{{ route('allocation.seat-breakup.template') }}">Generate Excel</a>
            </div>
        </form>
    </div>
    @if($seatVersions->isNotEmpty())
    <div class="table-responsive border-top"><table class="table table-vcenter mb-0"><thead><tr><th class="text-center">Version</th><th>Status</th><th class="text-center">Rows</th><th class="text-center">Total</th><th class="text-center">MQ</th><th class="text-center">CFF</th><th class="text-center">EM</th><th class="text-center">PHC</th><th>Hash</th><th></th></tr></thead><tbody>@foreach($seatVersions as $v)<tr><td>v{{ $v->version }}</td><td><span class="badge bg-{{ $v->status==='finalized'?'success':($v->status==='validated'?'azure':'secondary') }}-lt">{{ strtoupper($v->status) }}</span></td><td>{{ $v->total_rows }}</td><td>{{ $v->total_posts }}</td><td>{{ $v->mq_posts }}</td><td>{{ $v->cff_posts }}</td><td>{{ $v->em_posts }}</td><td>{{ $v->phc_posts }}</td><td class="small text-break" style="max-width:220px"><code>{{ $v->dataset_hash ?: '—' }}</code></td><td><div class="btn-list flex-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.seat-breakup.show',$v) }}">View Data</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('allocation.seat-breakup.pdf',$v) }}">PDF</a>@if($v->status==='validated')<form method="POST" action="{{ route('allocation.seat-breakup.finalize',$v) }}">@csrf<button class="btn btn-sm btn-success">Finalize / Freeze</button></form>@endif</div></td></tr>@endforeach</tbody></table></div>
    @endif
</div>

<div class="card mb-3" id="allocation-settings-card">
    <div class="card-header">
        <div><h3 class="card-title">A1 — Allocation Settings</h3><div class="card-subtitle">Read-only operational settings. Configure in <code>{{ $settingsInfo['config_file'] }}</code>, then freeze the current configuration before A2.</div></div>
        <div class="ms-auto text-end">
            <div class="small text-secondary mb-1">Configuration Status</div>
            <span class="badge bg-{{ $settingsInfo['matches_frozen'] ? 'success' : 'warning' }}-lt">{{ $settingsInfo['matches_frozen'] ? 'FROZEN & CURRENT' : 'REVIEW / FREEZE REQUIRED' }}</span>
        </div>
    </div>
    <div class="card-body">
        <div class="allocation-setting-line"><span class="allocation-setting-label">Quota Priority</span><span class="allocation-setting-value">{{ implode(' → ', $settingsInfo['current']['quota_priority']) }}</span></div>
        <div class="allocation-setting-line"><span class="allocation-setting-label">Provisional Target</span><span class="allocation-setting-value">MQ {{ $settingsInfo['current']['mq_percent'] }}% · CFF {{ $settingsInfo['current']['cff_percent'] }}% · EM {{ $settingsInfo['current']['em_percent'] }}% · PHC {{ $settingsInfo['current']['phc_percent'] }}%</span></div>
        <div class="allocation-setting-line mb-0"><span class="allocation-setting-label">Quota Breakup Minimum Total Post</span><span class="allocation-setting-value">{{ $settingsInfo['current']['quota_breakup_minimum_total_posts'] }}</span><span class="badge bg-secondary-lt ms-1">LOCKED RULE</span></div>
        <div class="small text-secondary mt-1">Quota breakup applies only when sanctioned total posts are 10 or more. For 1–9 posts, all seats are MQ and CFF/EM/PHC are zero.</div>
        <div class="alert alert-info mt-3 mb-0 py-2"><div class="fw-semibold">How to change</div><div class="small">Edit <code>config/allocation.php</code>, then run <code>php artisan config:clear</code> (or rebuild production config cache), review this card, and freeze the current config.</div></div>
        @if($settingsInfo['frozen_hash'])<div class="small text-secondary text-break mt-3">Frozen hash: <code>{{ $settingsInfo['frozen_hash'] }}</code></div>@endif
        <div class="small text-secondary text-break mt-1">Current config hash: <code>{{ $settingsInfo['current_hash'] }}</code></div>
    </div>
    <div class="card-footer"><form method="POST" action="{{ route('allocation.settings.finalize') }}">@csrf<button class="btn btn-primary">{{ $settingsInfo['matches_frozen'] ? 'Re-freeze Current Config' : 'Finalize / Freeze Current Config' }}</button></form></div>
</div>
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
            <thead><tr><th class="text-center">Version</th><th>Status</th><th>Choice Source</th><th class="text-center">Candidates</th><th class="text-center">Choice Ready</th><th class="text-center">Queue Entries</th><th>Queue Hash</th><th></th></tr></thead>
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
                @if($latestRun && (bool)$latestRun->is_stale)
                    <div class="alert alert-warning py-2 px-3 mt-2 mb-0"><strong>A3 STALE / OUTDATED.</strong> {{ $latestRun->stale_reason ?: 'Allocation inputs changed. Re-run Phase-1.' }}</div>
                @endif
            </div>
            <div class="col-md-4 text-md-end">
                <div class="d-inline-flex gap-2 justify-content-md-end align-items-center flex-nowrap">
                    <form class="m-0" method="POST" action="{{ route('allocation.phase-one.start') }}">
                        @csrf
                        <button class="btn btn-sm btn-primary text-nowrap text-uppercase" {{ $canStartPhase1 ? '' : 'disabled' }}>
                            {{ $latestRun && $latestRun->status === 'phase1_complete' && !(bool)$latestRun->is_stale ? 'Re-run Phase-1' : 'Start Phase-1' }}
                        </button>
                    </form>
                    @if($latestRun && $latestRun->status === 'phase1_complete')
                        <a class="btn btn-sm btn-success text-nowrap text-uppercase" href="{{ route('allocation.runs.show',$latestRun) }}">View Phase-1 Result</a>
                    @endif
                </div>
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
            <thead><tr><th>Run</th><th>Status</th><th>Allocated</th><th>Unallocated</th><th class="text-center">MQ</th><th class="text-center">CFF</th><th class="text-center">EM</th><th class="text-center">PHC</th><th>Iterations</th><th></th></tr></thead>
            <tbody>
            @foreach($allocationRuns as $run)
                <tr>
                    <td>v{{ $run->version }}</td>
                    <td>
                        @if((bool)$run->is_stale)
                            <span class="badge bg-warning-lt">STALE / OUTDATED</span>
                        @else
                            <span class="badge bg-{{ $run->status === 'phase1_complete' ? 'success' : ($run->status === 'failed' ? 'danger' : 'azure') }}-lt">{{ strtoupper(str_replace('_',' ', $run->status)) }}</span>
                        @endif
                    </td>
                    <td>{{ number_format($run->allocated_count) }}</td>
                    <td>{{ number_format($run->unallocated_count) }}</td>
                    <td>{{ number_format($run->mq_count) }}</td>
                    <td>{{ number_format($run->cff_count) }}</td>
                    <td>{{ number_format($run->em_count) }}</td>
                    <td>{{ number_format($run->phc_count) }}</td>
                    <td>{{ number_format($run->iteration_count) }}</td>
                    <td class="text-end">@if($run->status === 'phase1_complete')<a class="btn btn-sm btn-secondary" href="{{ route('allocation.runs.show',$run) }}">View Phase-1 Result</a>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@php
    $latestCompletedA3 = $allocationRuns->first(fn($r) => $r->status === 'phase1_complete' && !(bool)$r->is_stale);
    $latestA4 = $a4Runs->first();
    $a4Busy = $latestA4 && in_array((string)$latestA4->status, ['queued','running'], true);
@endphp
<div class="card mb-3" id="allocation-a4-card">
    <div class="card-header">
        <div>
            <h3 class="card-title">A4 — Phase-2 NM + Shifting</h3>
            <div class="card-subtitle">Consumes an immutable completed A3 run. Vacant/released quota capacity becomes pure merit/NM capacity; A3 evidence remains unchanged.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                @if($latestA4 && (bool)$latestA4->is_stale)
                    <div class="alert alert-warning py-2 px-3 mb-2"><strong>A4 STALE / OUTDATED.</strong> {{ $latestA4->stale_reason ?: 'A3 or Allocation inputs changed. Re-run Phase-2 after current A3 is ready.' }}</div>
                @endif
                @if($latestCompletedA3)
                    <div>Source Phase-1: <strong>v{{ $latestCompletedA3->version }}</strong> · Allocated {{ number_format($latestCompletedA3->allocated_count) }} · Temporary {{ number_format($latestCompletedA3->temporary_count) }}</div>
                    <div class="small text-secondary mt-1">A4 can only improve TEMPORARY allocations or allocate previously unallocated candidates; FINAL A3 candidates stay locked.</div>
                @else
                    <div class="text-secondary">Complete A3 Phase-1 before starting A4.</div>
                @endif
            </div>
            <div class="col-md-4 text-md-end">
                @if($a4Busy)
                    <a class="btn btn-warning" href="{{ route('allocation.a4.processing', $latestA4) }}">View A4 Processing</a>
                @elseif($latestCompletedA3)
                    <div class="d-inline-flex gap-2 justify-content-md-end align-items-center flex-nowrap">
                        
                        <form class="m-0" method="POST" action="{{ route('allocation.a4.start', $latestCompletedA3) }}">@csrf
                            <button class="btn btn-sm btn-primary text-nowrap text-uppercase" type="submit" {{ $readiness['ready'] ? '' : 'disabled' }}>
                                {{ $latestA4 ? 'Re-run Phase-2' : 'Start Phase-2' }}
                            </button>
                        </form>
						
						@if($latestA4 && $latestA4->status === 'a4_complete')
                            <a class="btn btn-sm btn-success text-nowrap text-uppercase" href="{{ route('allocation.a4.show', $latestA4) }}">View Phase-2 Result</a>
                        @endif
						
                    </div>
                    @if(!$readiness['ready'])
                        <div class="small text-danger mt-2">Pre-run Gate is BLOCKED. A4 Start/Re-Run is disabled until required upstream inputs are READY.</div>
                    @endif
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
                    <td>
                        @if((bool)$a4->is_stale)
                            <span class="badge bg-warning-lt">STALE / OUTDATED</span>
                        @else
                            <span class="badge bg-{{ $a4->status === 'a4_complete' ? 'success' : ($a4->status === 'failed' ? 'danger' : 'azure') }}-lt">{{ strtoupper(str_replace('_',' ', $a4->status)) }}</span>
                        @endif
                    </td>
                    <td>{{ number_format($a4->allocated_count) }}</td>
                    <td>{{ number_format($a4->unallocated_count) }}</td>
                    <td>{{ number_format($a4->nm_count) }}</td>
                    <td>{{ number_format($a4->shifted_count) }}</td>
                    <td>{{ number_format($a4->quota_to_merit_count) }}</td>
                    <td>{{ number_format($a4->iteration_count) }}</td>
                    <td class="text-end">
                        @if($a4->status === 'a4_complete')
                            <a class="btn btn-sm btn-secondary" href="{{ route('allocation.a4.show', $a4) }}">View Phase-2 Result</a>
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
@php
    $latestA5 = $a5Runs->first();
    $latestCurrentA4 = $a4Runs->first(fn($r) => $r->status === 'a4_complete' && !(bool)$r->is_stale);
    $a5Busy = $latestA5 && in_array((string)$latestA5->status, ['queued','running'], true);
@endphp
<div class="card mb-3" id="allocation-a5-card">
    <div class="card-header">
        <div>
            <h3 class="card-title">A5 — Final Allocation Validity Check</h3>
            <div class="card-subtitle">Read-only assurance gate over final A4 allocation: Circular bachelor/PRS requirements, technical eligibility, quota entitlement, and final cadre seat-limit validation.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                @if($latestA5 && (bool)$latestA5->is_stale)
                    <div class="alert alert-warning py-2 px-3 mb-2"><strong>A5 STALE / OUTDATED.</strong> {{ $latestA5->stale_reason ?: 'A4 or authoritative Allocation inputs changed. Re-run A5.' }}</div>
                @endif
                @if($latestCurrentA4)
                    <div>Current A4 source: <strong>v{{ $latestCurrentA4->version }}</strong> · Final Allocated {{ number_format($latestCurrentA4->allocated_count) }}</div>
                    <div class="small text-secondary mt-1">A5 never changes A4 allocation. Any mismatch blocks downstream Reporting/Export and must be corrected upstream.</div>
                @else
                    <div class="text-secondary">Complete a current, non-stale A4 Phase-2 result before running A5.</div>
                @endif
            </div>
            <div class="col-md-4 text-md-end">
                @if($a5Busy)
                    <a class="btn btn-sm btn-warning text-uppercase" href="{{ route('allocation.a5.processing',$latestA5) }}">View A5 Processing</a>
                @elseif($latestCurrentA4)
                    <div class="d-inline-flex gap-2 align-items-center flex-nowrap">
                        <form method="POST" action="{{ route('allocation.a5.start') }}" class="m-0">@csrf
                            <button class="btn btn-sm btn-primary text-uppercase text-nowrap" type="submit">{{ $latestA5 ? 'Re-run A5 Check' : 'Run A5 Check' }}</button>
                        </form>
                        @if($latestA5 && in_array((string)$latestA5->status,['validated_ok','validated_failed','finalized'],true))
                            <a class="btn btn-sm btn-success text-uppercase text-nowrap" href="{{ route('allocation.a5.show',$latestA5) }}">View A5 Report</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    @if($latestA5)
    <div id="a5-landing-progress-wrap" class="card-body border-top {{ $a5Busy || $latestA5->status === 'failed' ? '' : 'd-none' }}" data-status-url="{{ route('allocation.a5.status',$latestA5) }}">
        <div class="d-flex justify-content-between align-items-center mb-2"><div><strong id="a5-landing-phase">{{ strtoupper(str_replace('_',' ',$latestA5->phase ?: $latestA5->status)) }}</strong><div class="small text-secondary" id="a5-landing-message">{{ $latestA5->progress_message }}</div></div><span><span id="a5-landing-percent">{{ (int)$latestA5->progress_percent }}</span>%</span></div>
        <div class="progress"><div id="a5-landing-progress-bar" class="progress-bar" style="width:{{ (int)$latestA5->progress_percent }}%"></div></div>
        <div id="a5-landing-counter" class="small text-secondary mt-2 {{ (int)$latestA5->progress_total > 0 ? '' : 'd-none' }}">Processed {{ number_format($latestA5->progress_current) }} / {{ number_format($latestA5->progress_total) }}</div>
        <div id="a5-landing-error" class="alert alert-danger mt-3 mb-0 {{ $latestA5->status === 'failed' ? '' : 'd-none' }}">{{ $latestA5->failure_message }}</div>
    </div>
    @endif
    @if($a5Runs->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-vcenter mb-0">
            <thead><tr><th class="text-center">Run</th><th class="text-center">A4 Source</th><th>Status</th><th class="text-center">Allocated</th><th class="text-center">Candidate Fail</th><th class="text-center">Capacity Fail</th><th></th></tr></thead>
            <tbody>
            @foreach($a5Runs as $a5)
                <tr>
                    <td class="text-center">v{{ $a5->version }}</td>
                    <td class="text-center">A4 v{{ $a5->a4Run?->version ?? '—' }}</td>
                    <td>
                        @if((bool)$a5->is_stale)<span class="badge bg-warning-lt">STALE / OUTDATED</span>
                        @else<span class="badge bg-{{ $a5->status === 'finalized' ? 'success' : ($a5->status === 'validated_ok' ? 'azure' : (in_array($a5->status,['validated_failed','failed'],true) ? 'danger' : 'warning')) }}-lt">{{ strtoupper(str_replace('_',' ',(string)$a5->status)) }}</span>@endif
                    </td>
                    <td class="text-center">{{ number_format($a5->total_allocated) }}</td>
                    <td class="text-center">{{ number_format($a5->candidate_failed) }}</td>
                    <td class="text-center">{{ number_format($a5->capacity_failed) }}</td>
                    <td class="text-end">@if(in_array((string)$a5->status,['validated_ok','validated_failed','finalized'],true))<a class="btn btn-sm btn-secondary" href="{{ route('allocation.a5.show',$a5) }}">View A5 Report</a>@elseif(in_array((string)$a5->status,['queued','running','failed'],true))<a class="btn btn-sm btn-outline-secondary" href="{{ route('allocation.a5.processing',$a5) }}">View A5 Run</a>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
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
                // Refresh the server-rendered controls so a failed queued attempt
                // does not leave the Start Phase-1 button visually disabled.
                window.setTimeout(() => window.location.reload(), 700);
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

<script>
(function(){
    const initialBusy = @json($a5Busy);
    if(!initialBusy) return;
    const wrap=document.getElementById('a5-landing-progress-wrap'); if(!wrap) return;
    const url=wrap.dataset.statusUrl;
    async function pollA5(){try{
        const r=await fetch(url,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
        if(!r.ok) throw new Error('Unable to read A5 validity status.');
        const d=await r.json(); const pct=Number(d.progress_percent||0);
        document.getElementById('a5-landing-percent').textContent=pct;
        document.getElementById('a5-landing-progress-bar').style.width=pct+'%';
        document.getElementById('a5-landing-phase').textContent=String(d.phase||d.status||'').replaceAll('_',' ').toUpperCase();
        document.getElementById('a5-landing-message').textContent=d.message||'';
        const c=document.getElementById('a5-landing-counter');
        if(Number(d.progress_total||0)>0){c.textContent='Processed '+Number(d.progress_current||0).toLocaleString()+' / '+Number(d.progress_total||0).toLocaleString();c.classList.remove('d-none')}else c.classList.add('d-none');
        if(d.status==='failed'){const e=document.getElementById('a5-landing-error');e.textContent=d.error||'A5 failed.';e.classList.remove('d-none');return}
        if(!['queued','running'].includes(d.status)){window.location.reload();return}
        setTimeout(pollA5,1200);
    }catch(e){document.getElementById('a5-landing-message').textContent=e.message+' Retrying…';setTimeout(pollA5,2500)}}
    setTimeout(pollA5,500);
})();
</script>

{{-- A6 is a read-only downstream publishing layer. It stays visibly locked until A1-A5 are current and A5 is finalized at 100% PASS. --}}
<div class="container-xl">
<div class="card mt-3 mb-3" id="allocation-a6-card">
    <div class="card-header">
        <div><h3 class="card-title">A6 — Reporting &amp; Export</h3><div class="card-subtitle">Candidate reporting, cadre drill-down, TXT/XLSX export and DOCX publishing from the final validated Allocation result.</div></div>
        <div class="ms-auto"><span class="badge bg-{{ ($a6Gate['ready'] ?? false) ? 'success' : 'secondary' }}-lt">{{ ($a6Gate['ready'] ?? false) ? 'ACTIVE / READY' : 'INACTIVE / BLOCKED' }}</span></div>
    </div>
    <div class="card-body d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div class="text-secondary">@if($a6Gate['ready'] ?? false)A5 v{{ $a6Gate['a5_version'] }} finalized 100% PASS · A4 v{{ $a6Gate['a4_version'] }} · Circular v{{ $a6Gate['circular_version'] }}@else{{ $a6Gate['reason'] ?? 'Complete and finalize A1-A5 before Reporting & Export.' }}@endif</div>
        <a class="btn btn-primary {{ ($a6Gate['ready'] ?? false) ? '' : 'disabled' }}" href="{{ ($a6Gate['ready'] ?? false) ? route('allocation.a6.index') : '#' }}">Open Reporting &amp; Export</a>
    </div>
</div>
</div>

@endsection
