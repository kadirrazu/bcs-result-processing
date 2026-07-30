@extends('layouts.app')
@section('title', 'Import Result')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Import Batch #{{ $record->id }}</h2><div class="text-secondary">{{ $record->original_name }} · {{ str_replace('_', ' ', $record->status) }}</div></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('registrations.import.report', $record) }}">Download Full CSV Report</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
    <div class="row row-cards mb-3">
        @foreach(['total_rows'=>'Total','inserted_rows'=>'Inserted','updated_rows'=>'Updated','failed_rows'=>'Failed','warning_rows'=>'Warnings','identity_conflict_rows'=>'Identity conflicts'] as $field=>$label)
            <div class="col-sm-6 col-lg-2"><div class="card"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format($record->$field) }}</div></div></div></div>
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
        @forelse($rows as $row)<tr><td>{{ $row->source_row }}</td><td>{{ $row->reg }}</td><td>{{ $row->user_id }}</td><td>{{ str_replace('_', ' ', $row->action) }}</td><td>{{ implode(' | ', $row->errors ?? []) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">No rejected rows.</td></tr>@endforelse
    </tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div></div>
@endsection
