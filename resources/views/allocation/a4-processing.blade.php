@extends('layouts.app')
@section('content')
<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-3">
    <div>
        <h2 class="page-title">Allocation A4 — Run v{{ $a4Run->version }}</h2>
        <div class="text-secondary">Dedicated NM + Shifting processing screen. Source A3 v{{ $a4Run->phase1Run?->version ?? '—' }} remains immutable.</div>
    </div>
    <div class="btn-list">
        <a class="btn btn-outline-primary" href="{{ route('allocation.runs.show', $a4Run->phase1_run_id) }}">View Original A3</a>
        <a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a>
    </div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card" id="a4-processing-card" data-status-url="{{ route('allocation.a4.status', $a4Run) }}">
    <div class="card-header"><div><h3 class="card-title">A4 Processing Status</h3><div class="card-subtitle">Queue processing continues independently of this browser page.</div></div></div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="text-secondary">Status</div><div class="fw-bold" id="a4-status">{{ strtoupper($a4Run->status) }}</div></div>
            <div class="col-md-3"><div class="text-secondary">Phase</div><div class="fw-bold" id="a4-phase">{{ strtoupper(str_replace('_',' ', $a4Run->phase ?: '—')) }}</div></div>
            <div class="col-md-3"><div class="text-secondary">A3 Source</div><div class="fw-bold">v{{ $a4Run->phase1Run?->version ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-secondary">A4 Version</div><div class="fw-bold">v{{ $a4Run->version }}</div></div>
        </div>
        <div class="d-flex justify-content-between mb-2"><span id="a4-message">{{ $a4Run->progress_message }}</span><strong><span id="a4-percent">{{ (int)$a4Run->progress_percent }}</span>%</strong></div>
        <div class="progress"><div id="a4-bar" class="progress-bar" style="width:{{ (int)$a4Run->progress_percent }}%"></div></div>
        <div id="a4-counter" class="small text-secondary mt-2 {{ (int)$a4Run->progress_total > 0 ? '' : 'd-none' }}">Processed {{ number_format($a4Run->progress_current) }} / {{ number_format($a4Run->progress_total) }}</div>
        <div id="a4-error" class="alert alert-danger mt-3 mb-0 {{ $a4Run->status === 'failed' ? '' : 'd-none' }}">{{ $a4Run->failure_message }}</div>
        <div id="a4-complete" class="mt-3 d-none"><a class="btn btn-success" href="{{ route('allocation.a4.show', $a4Run) }}">View A4 Result</a></div>
    </div>
</div>
</div></div>
<script>
(() => {
    const card = document.getElementById('a4-processing-card');
    const url = card.dataset.statusUrl;
    const terminal = ['failed','a4_complete','superseded'];
    async function poll() {
        try {
            const r = await fetch(url, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
            if (!r.ok) throw new Error('Unable to read A4 processing status.');
            const d = await r.json();
            document.getElementById('a4-status').textContent = String(d.status || '').replaceAll('_',' ').toUpperCase();
            document.getElementById('a4-phase').textContent = String(d.phase || '').replaceAll('_',' ').toUpperCase();
            document.getElementById('a4-message').textContent = d.message || '';
            document.getElementById('a4-percent').textContent = Number(d.progress_percent || 0);
            document.getElementById('a4-bar').style.width = Number(d.progress_percent || 0) + '%';
            const counter = document.getElementById('a4-counter');
            if (Number(d.progress_total || 0) > 0) {
                counter.textContent = 'Processed ' + Number(d.progress_current || 0).toLocaleString() + ' / ' + Number(d.progress_total || 0).toLocaleString();
                counter.classList.remove('d-none');
            } else counter.classList.add('d-none');
            if (d.error) { const e=document.getElementById('a4-error'); e.textContent=d.error; e.classList.remove('d-none'); }
            if (d.view_url) { document.getElementById('a4-complete').classList.remove('d-none'); window.location.href=d.view_url; return; }
            if (!terminal.includes(d.status)) setTimeout(poll, 1200);
        } catch (e) {
            document.getElementById('a4-message').textContent = e.message + ' Retrying…';
            setTimeout(poll, 2500);
        }
    }
    if (!terminal.includes(@json((string)$a4Run->status))) setTimeout(poll, 500);
})();
</script>
@endsection
