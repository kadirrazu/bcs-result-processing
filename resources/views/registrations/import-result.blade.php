@extends('layouts.app')
@section('title', 'Import Result')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Import Batch #{{ $record->id }}</h2><div class="text-secondary">{{ $record->original_name }} · <span id="batch-status">{{ str_replace('_', ' ', $record->status) }}</span></div></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('registrations.import.report', $record) }}">Download Full CSV Report</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
    <div class="card mb-3" id="progress-card">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2"><strong>Import Progress</strong><span id="progress-label">{{ number_format((float) $record->progress_percent, 2) }}%</span></div>
            <div class="progress progress-lg"><div id="progress-bar" class="progress-bar" style="width: {{ min(100, (float) $record->progress_percent) }}%"></div></div>
            <div class="text-secondary mt-2"><span id="processed-count">{{ number_format($record->processed_rows) }}</span> / <span id="total-count">{{ number_format($record->total_rows) }}</span> rows · Chunk <span id="chunk-count">{{ number_format($record->current_chunk) }}</span> / <span id="total-chunks">{{ number_format($record->total_chunks) }}</span></div>
            <div id="failure-message" class="alert alert-danger mt-3 mb-0 {{ $record->failure_message ? '' : 'd-none' }}">{{ $record->failure_message }}</div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        @foreach(['total_rows'=>'Total','inserted_rows'=>'Inserted','updated_rows'=>'Updated','failed_rows'=>'Failed','warning_rows'=>'Warnings','identity_conflict_rows'=>'Identity conflicts'] as $field=>$label)
            <div class="col-sm-6 col-lg-2"><div class="card"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0" id="metric-{{ $field }}">{{ number_format($record->$field) }}</div></div></div></div>
        @endforeach
    </div>

    @if($record->rolled_back_at)
        <div class="alert alert-warning">Rolled back on {{ $record->rolled_back_at->format('d-m-Y H:i:s') }}. {{ $record->rollback_reason }}</div>
    @elseif(in_array($record->status, ['completed','completed_with_errors'], true))
        <div class="card mb-3"><div class="card-header"><h3 class="card-title">Rollback batch</h3></div><div class="card-body"><form method="post" action="{{ route('registrations.import.rollback', $record) }}" onsubmit="return confirm('Rollback this batch? New rows will be deleted and updated rows restored.');">@csrf
            <textarea class="form-control mb-2" name="reason" maxlength="2000" placeholder="Rollback reason (optional)"></textarea><button class="btn btn-danger">Rollback Batch</button>
        </form></div></div>
    @endif

    <div class="card"><div class="card-header"><h3 class="card-title">Rejected and identity-conflict rows</h3></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Row</th><th>Reg</th><th>User ID</th><th>Action</th><th>Errors</th></tr></thead><tbody>
        @forelse($rows as $row)<tr><td>{{ $row->source_row }}</td><td>{{ $row->reg }}</td><td>{{ $row->user_id }}</td><td>{{ str_replace('_', ' ', $row->action) }}</td><td>{{ implode(' | ', $row->errors ?? []) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">No rejected rows yet.</td></tr>@endforelse
    </tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div></div>
@endsection

@push('scripts')
@if(in_array($record->status, ['queued', 'processing'], true))
<script>
(() => {
    const statusUrl = @json(route('registrations.import.status', $record));
    const format = value => new Intl.NumberFormat().format(value ?? 0);

    const refresh = async () => {
        try {
            const response = await fetch(statusUrl, {headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            document.getElementById('batch-status').textContent = data.status.replaceAll('_', ' ');
            document.getElementById('progress-label').textContent = Number(data.progress_percent).toFixed(2) + '%';
            document.getElementById('progress-bar').style.width = Math.min(100, Number(data.progress_percent)) + '%';
            document.getElementById('processed-count').textContent = format(data.processed_rows);
            document.getElementById('total-count').textContent = format(data.total_rows);
            document.getElementById('chunk-count').textContent = format(data.current_chunk);
            document.getElementById('total-chunks').textContent = format(data.total_chunks);
            for (const key of ['total_rows','inserted_rows','updated_rows','failed_rows','warning_rows']) {
                const element = document.getElementById('metric-' + key);
                if (element) element.textContent = format(data[key]);
            }
            if (data.failure_message) {
                const failure = document.getElementById('failure-message');
                failure.textContent = data.failure_message;
                failure.classList.remove('d-none');
            }
            if (data.finished) window.setTimeout(() => window.location.reload(), 800);
            else window.setTimeout(refresh, 2000);
        } catch (_) {
            window.setTimeout(refresh, 5000);
        }
    };
    window.setTimeout(refresh, 1000);
})();
</script>
@endif
@endpush
