@extends('layouts.app')
@section('title', 'Import Result')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Import Batch #{{ $record->id }}</h2><div class="text-secondary">{{ $record->original_name }} · <span id="batch-status">{{ str_replace('_', ' ', $record->status) }}</span></div></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('registrations.import.report', $record) }}">Download CSV Report</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between mb-2"><strong>Current Phase Progress</strong><span id="progress-label">{{ number_format((float) $record->progress_percent, 2) }}%</span></div>
        <div class="progress progress-lg"><div id="progress-bar" class="progress-bar" style="width: {{ min(100, (float) $record->progress_percent) }}%"></div></div>
        <div class="text-secondary mt-2"><span id="processed-count">{{ number_format($record->processed_rows) }}</span> processed · <span id="total-count">{{ number_format($record->total_rows) }}</span> source rows</div>
        <div id="failure-message" class="alert alert-danger mt-3 mb-0 {{ $record->failure_message ? '' : 'd-none' }}">{{ $record->failure_message }}</div>
    </div></div>

    <div class="row row-cards mb-3">
        @foreach(['total_rows'=>'Source','staged_rows'=>'Staged','valid_rows'=>'Valid','warning_rows'=>'Warnings','invalid_rows'=>'Invalid','approved_rows'=>'Approved'] as $field=>$label)
            <div class="col-sm-6 col-lg-2"><div class="card"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0" id="metric-{{ $field }}">{{ number_format($record->$field) }}</div></div></div></div>
        @endforeach
    </div>

    @if($record->status === 'staged')
        <div class="alert alert-info d-flex align-items-center justify-content-between">
            <div>Fast staging is complete. Run database validation before merging registrations.</div>
            <form method="post" action="{{ route('registrations.import.validate', $record) }}">@csrf<button class="btn btn-primary">Validate Staged Data</button></form>
        </div>
    @elseif($record->status === 'validated')
        <div class="alert alert-warning d-flex align-items-center justify-content-between">
            <div>{{ number_format($record->valid_rows + $record->warning_rows) }} rows are eligible for approval; {{ number_format($record->invalid_rows) }} rows will remain unmerged.</div>
            <form method="post" action="{{ route('registrations.import.approve', $record) }}" onsubmit="return confirm('Merge all valid and warning rows into registrations?');">@csrf<button class="btn btn-success">Approve & Merge</button></form>
        </div>
    @elseif($record->status === 'failed' && (int) $record->approved_rows === 0 && ((int) $record->valid_rows + (int) $record->warning_rows) > 0)
        <div class="alert alert-danger d-flex align-items-center justify-content-between">
            <div>The approval job failed before any merge chunk was committed. The existing validated staging data can be retried safely.</div>
            <form method="post" action="{{ route('registrations.import.approve', $record) }}" onsubmit="return confirm('Retry merging all eligible staged rows into registrations?');">@csrf<button class="btn btn-danger">Retry Approve &amp; Merge</button></form>
        </div>
    @elseif($record->status === 'approved')
        <div class="alert alert-success">Approval completed: {{ number_format($record->inserted_rows) }} inserted and {{ number_format($record->updated_rows) }} updated.</div>
    @endif

    @if($record->rolled_back_at)
        <div class="alert alert-warning">Rolled back on {{ $record->rolled_back_at->format('d-m-Y H:i:s') }}. {{ $record->rollback_reason }}</div>
    @elseif(in_array($record->status, ['staged','validated','approved','failed'], true))
        <div class="card mb-3"><div class="card-header"><h3 class="card-title">Rollback batch</h3></div><div class="card-body"><form method="post" action="{{ route('registrations.import.rollback', $record) }}" onsubmit="return confirm('Rollback this batch?');">@csrf
            <textarea class="form-control mb-2" name="reason" maxlength="2000" placeholder="Rollback reason (optional)"></textarea><button class="btn btn-danger">Rollback Batch</button>
        </form></div></div>
    @endif

    <div class="card"><div class="card-header"><h3 class="card-title">Invalid and warning rows</h3></div><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>Row</th><th>Reg</th><th>User ID</th><th>Status</th><th>Messages</th></tr></thead><tbody>
        @forelse($rows as $row)<tr><td>{{ $row->source_row }}</td><td>{{ $row->reg }}</td><td>{{ $row->user_id }}</td><td>{{ $row->validation_status }}</td><td>{{ implode(' | ', array_merge($row->validation_errors ?? [], $row->validation_warnings ?? [])) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">No invalid or warning rows yet.</td></tr>@endforelse
    </tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div></div>
@endsection

@push('scripts')
@if(in_array($record->status, ['queued','staging','validation_queued','validating','approval_queued','approving'], true))
<script>
(() => {
    const statusUrl = @json(route('registrations.import.status', $record));
    const format = value => new Intl.NumberFormat().format(value ?? 0);
    const keys = ['total_rows','staged_rows','valid_rows','warning_rows','invalid_rows','approved_rows'];
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
            for (const key of keys) {
                const element = document.getElementById('metric-' + key);
                if (element) element.textContent = format(data[key]);
            }
            if (data.failure_message) {
                const failure = document.getElementById('failure-message');
                failure.textContent = data.failure_message;
                failure.classList.remove('d-none');
            }
            if (data.finished) setTimeout(() => window.location.reload(), 800);
            else setTimeout(refresh, 2000);
        } catch (_) {
            setTimeout(refresh, 5000);
        }
    };
    setTimeout(refresh, 1000);
})();
</script>
@endif
@endpush
