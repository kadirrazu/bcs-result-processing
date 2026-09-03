@extends('layouts.app')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Google Form Historical Recommendations</h2>
                <div class="text-secondary">Batch #{{ $batch->id }} · {{ $batch->original_name }} · <span class="badge {{ $isLatestBatch ? 'bg-green-lt' : 'bg-secondary-lt' }}">{{ $isLatestBatch ? 'LATEST / AUTHORITY CANDIDATE' : 'HISTORY ONLY' }}</span></div>
            </div>
            <div class="col-auto"><a href="{{ route('choice-optimization.index') }}" class="btn btn-outline-secondary">Back</a></div>
        </div>
    </div>
</div>

<div class="page-body">
<div class="container-xl">
    <div class="row row-cards mb-3">
        <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Status</div><div class="h3 mb-0" id="gf-status">{{ strtoupper(str_replace('_',' ',$batch->status)) }}</div></div></div></div>
        <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Rows</div><div class="h3 mb-0" id="gf-total">{{ number_format($batch->total_rows) }}</div></div></div></div>
        <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Valid</div><div class="h3 mb-0" id="gf-valid">{{ number_format($batch->valid_rows) }}</div></div></div></div>
        <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Invalid</div><div class="h3 mb-0" id="gf-invalid">{{ number_format($batch->invalid_rows) }}</div></div></div></div>
        <div class="col-md"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Merged</div><div class="h3 mb-0" id="gf-merged">{{ number_format($batch->merged_rows) }}</div></div></div></div>
    </div>

    @php
        $isRunning = in_array($batch->status,['queued','processing','validation_queued','validating','merge_queued','merging'],true);
        $initialPhase = match($batch->status) {
            'queued','processing' => 'Staging',
            'validation_queued','validating' => 'Validation',
            'merge_queued','merging' => 'Merge',
            default => 'Completed',
        };
        $initialTarget = in_array($batch->status,['merge_queued','merging'],true) ? (int) $batch->valid_rows : (int) $batch->total_rows;
    @endphp

    <div class="card mb-3 {{ $isRunning ? '' : 'd-none' }}" id="gf-progress-card">
        <div class="card-body">
            <div class="d-flex align-items-center mb-2">
                <div>
                    <div class="fw-semibold" id="gf-phase">{{ $initialPhase }}</div>
                    <div class="text-secondary small" id="gf-progress-text">
                        {{ number_format($batch->processed_rows) }} / {{ number_format($initialTarget) }} processed
                    </div>
                </div>
                <div class="ms-auto fw-semibold" id="gf-percent">{{ number_format((float)$batch->progress_percent,1) }}%</div>
            </div>
            <div class="progress progress-sm">
                <div class="progress-bar" id="gf-progress-bar" style="width: {{ max(0,min(100,(float)$batch->progress_percent)) }}%" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ max(0,min(100,(float)$batch->progress_percent)) }}"></div>
            </div>
            <div class="text-danger small mt-2 d-none" id="gf-failure"></div>
        </div>
    </div>

    @if(!$isLatestBatch)<div class="alert alert-secondary mb-3">This is an older Google Form batch. It is retained for history/audit only and cannot be validated or merged again.</div>@endif

    <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
        @if($isLatestBatch && in_array($batch->status,['staged','validation_failed'],true))
            <form method="POST" action="{{ route('choice-optimization.google-form.validate',$batch) }}">@csrf<button class="btn btn-primary">Validate</button></form>
        @endif
        @if($isLatestBatch && $batch->status==='validated' && $batch->valid_rows>0)
            <form method="POST" action="{{ route('choice-optimization.google-form.merge-valid',$batch) }}">@csrf<button class="btn btn-success">Merge Valid Rows</button></form>
        @endif
        @if($batch->invalid_rows>0)
            <a class="btn btn-outline-danger" href="{{ route('choice-optimization.google-form.invalid-rows',$batch) }}">Download Invalid Rows</a>
            <span class="text-secondary small">Correct the source and upload a complete replacement file as a new batch. The new latest batch replaces all older Google Form batches for optimization.</span>
        @endif
    </div></div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Rows</h3>
            <div class="ms-auto btn-list">
                <a class="btn btn-sm {{ $status===''?'btn-primary':'btn-outline-primary' }}" href="{{ route('choice-optimization.google-form.show',$batch) }}">All</a>
                <a class="btn btn-sm {{ $status==='valid'?'btn-success':'btn-outline-success' }}" href="{{ route('choice-optimization.google-form.show',[$batch,'status'=>'valid']) }}">Valid</a>
                <a class="btn btn-sm {{ $status==='invalid'?'btn-danger':'btn-outline-danger' }}" href="{{ route('choice-optimization.google-form.show',[$batch,'status'=>'invalid']) }}">Invalid</a>
            </div>
        </div>
        <div class="table-responsive"><table class="table table-vcenter card-table">
            <thead><tr><th>Row</th><th>Current Reg</th><th>Previous BCS</th><th>Cadre</th><th>Validation</th><th>Merge</th><th>Reason / Warning</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->source_row }}</td>
                    <td><code>{{ $row->raw_reg ?: '—' }}</code></td>
                    <td>{{ $row->raw_bcs ?: '—' }}</td>
                    <td><code>{{ $row->raw_cadre ?: '—' }}</code></td>
                    <td><span class="badge {{ $row->validation_status==='valid'?'bg-green-lt':($row->validation_status==='invalid'?'bg-red-lt':'bg-secondary-lt') }}">{{ strtoupper($row->validation_status) }}</span></td>
                    <td><span class="badge {{ $row->merge_status==='merged'?'bg-green-lt':'bg-secondary-lt' }}">{{ strtoupper($row->merge_status) }}</span></td>
                    <td>
                        @foreach((array)$row->validation_errors as $error)<div class="text-danger small">{{ $error['code'] ?? '' }} — {{ $error['message'] ?? '' }}</div>@endforeach
                        @foreach((array)$row->validation_warnings as $warning)<div class="text-warning small">{{ $warning['code'] ?? '' }} — {{ $warning['message'] ?? '' }}</div>@endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">No rows found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
</div>
</div>

<script>
(() => {
    const url = {{ Illuminate\Support\Js::from(route('choice-optimization.google-form.status', $batch)) }};
    const initiallyRunning = {{ Illuminate\Support\Js::from($isRunning) }};
    if (!initiallyRunning) return;

    const el = id => document.getElementById(id);
    const number = value => new Intl.NumberFormat().format(Number(value || 0));
    const clamp = value => Math.max(0, Math.min(100, Number(value || 0)));
    let timer = null;

    const render = data => {
        const pct = clamp(data.progress_percent);
        el('gf-progress-card')?.classList.remove('d-none');
        if (el('gf-status')) el('gf-status').textContent = data.status_label || data.status;
        if (el('gf-total')) el('gf-total').textContent = number(data.total_rows);
        if (el('gf-valid')) el('gf-valid').textContent = number(data.valid_rows);
        if (el('gf-invalid')) el('gf-invalid').textContent = number(data.invalid_rows);
        if (el('gf-merged')) el('gf-merged').textContent = number(data.merged_rows);
        if (el('gf-phase')) el('gf-phase').textContent = data.phase || 'Processing';
        if (el('gf-progress-text')) el('gf-progress-text').textContent = `${number(data.current)} / ${number(data.target)} processed`;
        if (el('gf-percent')) el('gf-percent').textContent = `${pct.toFixed(1)}%`;
        if (el('gf-progress-bar')) {
            el('gf-progress-bar').style.width = `${pct}%`;
            el('gf-progress-bar').setAttribute('aria-valuenow', String(pct));
        }
        if (data.failure_message && el('gf-failure')) {
            el('gf-failure').textContent = data.failure_message;
            el('gf-failure').classList.remove('d-none');
        }
    };

    const poll = async () => {
        try {
            const response = await fetch(url, {
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                cache: 'no-store',
            });
            if (!response.ok) return;
            const data = await response.json();
            render(data);
            if (!data.running) {
                if (timer) window.clearInterval(timer);
                window.setTimeout(() => window.location.reload(), 350);
            }
        } catch (_) {
            // Keep the page usable; the next polling cycle can recover.
        }
    };

    poll();
    timer = window.setInterval(poll, 1000);
})();
</script>
@endsection
