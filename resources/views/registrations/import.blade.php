@extends('layouts.app')
@section('title', 'Import Registrations')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Registration Import Console</h2><div class="text-secondary">Auditable insert/update processing with row reports and batch rollback.</div></div></div></div></div>
<div class="page-body"><div class="container-xl">
    <div class="card mb-3"><div class="card-body">
        <p>Use the official template. The importer validates fixed headers, streams rows in chunks, rejects identity conflicts, and records all inserts, updates, warnings and errors.</p>
        <a class="btn btn-outline-secondary mb-3" href="{{ route('registrations.import.template') }}">Download Template</a>
        <form method="post" enctype="multipart/form-data" action="{{ route('registrations.import.store') }}">@csrf
            <input class="form-control mb-3 @error('file') is-invalid @enderror" type="file" name="file" accept=".xlsx,.xls" required>
            @error('file')<div class="invalid-feedback mb-3">{{ $message }}</div>@enderror
            <button class="btn btn-primary">Start Import</button>
        </form>
    </div></div>

    <div class="card"><div class="card-header"><h3 class="card-title">Import History</h3></div><div class="table-responsive"><table class="table table-vcenter">
        <thead><tr><th>Batch</th><th>File</th><th>Status</th><th>Total</th><th>Inserted</th><th>Updated</th><th>Failed</th><th>Warnings</th><th>Started</th><th></th></tr></thead>
        <tbody>@forelse($batches as $batch)<tr>
            <td>#{{ $batch->id }}</td><td>{{ $batch->original_name }}</td><td><span class="badge">{{ str_replace('_', ' ', $batch->status) }}</span></td>
            <td>{{ number_format($batch->total_rows) }}</td><td>{{ number_format($batch->inserted_rows) }}</td><td>{{ number_format($batch->updated_rows) }}</td><td>{{ number_format($batch->failed_rows) }}</td><td>{{ number_format($batch->warning_rows) }}</td>
            <td>{{ $batch->started_at?->format('d-m-Y H:i:s') }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('registrations.import-result', $batch) }}">View</a></td>
        </tr>@empty<tr><td colspan="10" class="text-center text-secondary py-4">No import batches yet.</td></tr>@endforelse</tbody>
    </table></div>@if($batches->hasPages())<div class="card-footer">{{ $batches->links() }}</div>@endif</div>
</div></div>
@endsection
