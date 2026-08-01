@extends('layouts.app')

@section('title', 'Preliminary Processing')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Preliminary Processing</h2>
                <div class="text-secondary">Fast staging → Validation → Approval/Merge → Reconciliation → Cut-off → Finalization</div>
            </div>
            <div class="col-auto ms-auto"><a href="{{ route('preliminary.template') }}" class="btn btn-outline-primary">Download Import Template</a></div>
        </div>
    </div>
</div>

<div class="page-body"><div class="container-xl">
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Processing Status Board</h3></div>
        <div class="table-responsive"><table class="table table-vcenter card-table">
            <thead><tr><th>Step</th><th>Status / Summary</th><th>Completed At</th></tr></thead>
            <tbody>
                <tr><td>Mark Imported</td><td>{{ $latestBatch?->status ?? 'Not started' }} @if($latestBatch) — {{ number_format((int)$latestBatch->approved_rows) }} approved rows @endif</td><td>{{ $latestBatch?->approved_at?->format('d-m-Y h:i A') ?? '—' }}</td></tr>
                <tr><td>Present / Absent Report</td><td>{{ $state->reconciliation_generated_at ? 'Generated' : 'Pending' }}</td><td>{{ $state->reconciliation_generated_at?->format('d-m-Y h:i A') ?? '—' }}</td></tr>
                <tr><td>Cut-off Mark</td><td>{{ $state->cutoff_mark !== null ? 'Set as '.$state->cutoff_mark : 'Pending' }}</td><td>{{ $state->cutoff_set_at?->format('d-m-Y h:i A') ?? '—' }}</td></tr>
                <tr><td>Result Finalized</td><td>{{ $state->result_finalized_at ? 'Completed' : 'Pending' }}</td><td>{{ $state->result_finalized_at?->format('d-m-Y h:i A') ?? '—' }}</td></tr>
            </tbody>
        </table></div>
    </div>

    <div class="row row-cards mb-3">
        @foreach(['Results'=>$counts['results'],'Active with mark'=>$counts['active'],'Cancelled'=>$counts['cancelled'],'Passed'=>$counts['passed'],'Failed'=>$counts['failed']] as $label=>$value)
        <div class="col-sm-6 col-lg"><div class="card card-sm"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format($value) }}</div></div></div></div>
        @endforeach
    </div>

    @can('process', App\Models\PreliminaryResult::class)
    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Preliminary Mark Import</h3></div>
        <div class="card-body">
            <p class="text-secondary">Use exactly four columns: <code>user</code>, <code>reg</code>, <code>mark</code>, <code>candidate_status</code>. The file is staged first; validation and approval are separate queue phases.</p>
            <form method="post" enctype="multipart/form-data" action="{{ route('preliminary.import.store') }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md"><label class="form-label">XLSX / CSV file</label><input class="form-control @error('file') is-invalid @enderror" type="file" name="file" accept=".xlsx,.csv" required>@error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-auto"><button class="btn btn-primary">Queue Fast Import</button></div>
                </div>
            </form>
        </div>
    </div>
    @endcan

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Import History</h3></div>
        <div class="table-responsive"><table class="table table-vcenter card-table">
            <thead><tr><th>Batch</th><th>File</th><th>Status</th><th>Rows</th><th>Valid</th><th>Warnings</th><th>Invalid</th><th>Approved</th><th></th></tr></thead>
            <tbody>
                @forelse($batches as $batch)
                <tr>
                    <td>#{{ $batch->id }}</td><td>{{ $batch->original_name }}</td><td>{{ str_replace('_', ' ', $batch->status) }}</td>
                    <td>{{ number_format($batch->total_rows) }}</td><td>{{ number_format($batch->valid_rows) }}</td><td>{{ number_format($batch->warning_rows) }}</td><td>{{ number_format($batch->invalid_rows) }}</td><td>{{ number_format($batch->approved_rows) }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('preliminary.import.result', $batch) }}">View</a></td>
                </tr>
                @empty<tr><td colspan="9" class="text-center text-secondary py-4">No preliminary import batches yet.</td></tr>@endforelse
            </tbody>
        </table></div>
        @if($batches->hasPages())<div class="card-footer">{{ $batches->links() }}</div>@endif
    </div>

    <div class="card"><div class="card-header"><h3 class="card-title">Recent Preliminary Audit</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Action</th><th>Actor</th><th>Reason</th><th>Time</th></tr></thead><tbody>
        @forelse($audits as $audit)<tr><td>{{ $audit->action }}</td><td>{{ $audit->actor_name ?? $audit->actor_id }}</td><td>{{ $audit->reason ?? '—' }}</td><td>{{ $audit->created_at?->format('d-m-Y h:i A') }}</td></tr>@empty<tr><td colspan="4" class="text-center text-secondary">No audit entries yet.</td></tr>@endforelse
    </tbody></table></div></div>
</div></div>
@endsection
