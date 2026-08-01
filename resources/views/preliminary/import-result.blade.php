@extends('layouts.app')
@section('title', 'Preliminary Import Result')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center">
    <div class="col"><h2 class="page-title">Preliminary Import Batch #{{ $record->id }}</h2><div class="text-secondary">{{ $record->original_name }} · <span id="batch-status">{{ str_replace('_', ' ', $record->status) }}</span></div></div>
    <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('preliminary.import.report', $record) }}">Download Issue CSV</a></div>
</div></div></div>

<div class="page-body"><div class="container-xl">
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between mb-2"><strong>Current Phase Progress</strong><span id="progress-label">{{ number_format((float)$record->progress_percent, 2) }}%</span></div>
        <div class="progress progress-lg"><div id="progress-bar" class="progress-bar" style="width: {{ min(100, (float)$record->progress_percent) }}%"></div></div>
        <div class="text-secondary mt-2"><span id="processed-count">{{ number_format($record->processed_rows) }}</span> processed · <span id="total-count">{{ number_format($record->total_rows) }}</span> rows</div>
        <div id="failure-message" class="alert alert-danger mt-3 mb-0 {{ $record->failure_message ? '' : 'd-none' }}">{{ $record->failure_message }}</div>
    </div></div>

    <div class="row row-cards mb-3">
        @foreach(['total_rows'=>'Source','staged_rows'=>'Staged','valid_rows'=>'Valid','warning_rows'=>'Warnings','invalid_rows'=>'Invalid','identity_conflict_rows'=>'Identity conflict','approved_rows'=>'Approved'] as $field=>$label)
        <div class="col-sm-6 col-lg"><div class="card card-sm"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0" id="metric-{{ $field }}">{{ number_format($record->$field) }}</div></div></div></div>
        @endforeach
    </div>

    @if($record->status === 'staged')
        <div class="alert alert-info d-flex align-items-center justify-content-between"><div>Fast staging is complete. Validate identity, mark and candidate-status rules now.</div><form method="post" action="{{ route('preliminary.import.validate', $record) }}">@csrf<button class="btn btn-primary">Validate Staged Data</button></form></div>
    @elseif($record->status === 'validated')
        <div class="alert alert-warning">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap"><div><strong>{{ number_format($record->valid_rows + $record->warning_rows) }}</strong> rows are eligible for approval. {{ number_format($record->invalid_rows) }} rows will remain unmerged.</div><div class="d-flex gap-2"><form method="post" action="{{ route('preliminary.import.validate', $record) }}">@csrf<button class="btn btn-outline-primary">Revalidate</button></form><form method="post" action="{{ route('preliminary.import.approve', $record) }}" onsubmit="return confirm('Approve this preliminary snapshot and merge all valid/warning rows?');">@csrf<button class="btn btn-success">Approve &amp; Merge</button></form></div></div>
        </div>
    @elseif($record->status === 'failed' && (int)$record->approved_rows === 0)
        <div class="alert alert-danger d-flex align-items-center justify-content-between gap-2 flex-wrap"><div>The last queue phase failed before approval completed. Existing staging can be reused.</div><div class="d-flex gap-2"><form method="post" action="{{ route('preliminary.import.validate', $record) }}">@csrf<button class="btn btn-outline-danger">Retry Validation</button></form>@if(((int)$record->valid_rows + (int)$record->warning_rows) > 0)<form method="post" action="{{ route('preliminary.import.approve', $record) }}">@csrf<button class="btn btn-danger">Retry Approve &amp; Merge</button></form>@endif</div></div>
    @elseif($record->status === 'approved')
        <div class="alert alert-success">Preliminary snapshot approved: {{ number_format($record->inserted_rows) }} inserted, {{ number_format($record->updated_rows) }} updated. Candidates not present in this approved source are now derived as absent.</div>
    @endif

    <div class="card"><div class="card-header"><h3 class="card-title">Rows requiring attention</h3></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Row</th><th>Reg</th><th>User</th><th>Mark</th><th>Source status</th><th>Validation</th><th>Messages</th></tr></thead><tbody>
        @forelse($rows as $row)
        <tr><td>{{ $row->source_row }}</td><td>{{ $row->reg }}</td><td>{{ $row->user_id }}</td><td>{{ $row->raw_mark ?? '—' }}</td><td>{{ $row->raw_candidate_status ?? '—' }}</td><td>{{ str_replace('_', ' ', $row->validation_status) }}</td><td>{{ implode(' | ', array_merge($row->validation_errors ?? [], $row->validation_warnings ?? [])) }}</td></tr>
        @empty<tr><td colspan="7" class="text-center text-secondary py-4">No invalid or warning rows.</td></tr>@endforelse
    </tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div></div>
@endsection

@push('scripts')
@if(in_array($record->status, ['queued','staging','validation_queued','validating','approval_queued','approving'], true))
<script>
(() => {
    const statusUrl = @json(route('preliminary.import.status', $record));
    const format = value => new Intl.NumberFormat().format(value ?? 0);
    const keys = ['total_rows','staged_rows','valid_rows','warning_rows','invalid_rows','identity_conflict_rows','approved_rows'];
    const refresh = async () => {
        try {
            const response = await fetch(statusUrl, {headers: {'Accept': 'application/json'}});
            if (!response.ok) return setTimeout(refresh, 4000);
            const data = await response.json();
            document.getElementById('batch-status').textContent = data.status.replaceAll('_', ' ');
            document.getElementById('progress-label').textContent = Number(data.progress_percent).toFixed(2) + '%';
            document.getElementById('progress-bar').style.width = Math.min(100, Number(data.progress_percent)) + '%';
            document.getElementById('processed-count').textContent = format(data.processed_rows);
            document.getElementById('total-count').textContent = format(data.total_rows);
            for (const key of keys) { const element = document.getElementById('metric-' + key); if (element) element.textContent = format(data[key]); }
            if (data.failure_message) { const failure = document.getElementById('failure-message'); failure.textContent = data.failure_message; failure.classList.remove('d-none'); }
            if (data.finished) setTimeout(() => window.location.reload(), 700); else setTimeout(refresh, 1500);
        } catch (_) { setTimeout(refresh, 5000); }
    };
    setTimeout(refresh, 1000);
})();
</script>
@endif
@endpush
