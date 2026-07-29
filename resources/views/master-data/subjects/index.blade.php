@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col"><div class="page-pretitle">{{ $pretitle }}</div><h2 class="page-title">{{ $title }}</h2></div>
            <div class="col-auto ms-auto d-flex flex-wrap gap-2">
                <a href="{{ route('master-data.exports.pdf', $importType) }}" class="btn btn-outline-danger">Export PDF</a>
                <a href="{{ route('master-data.exports.excel', $importType) }}" class="btn btn-outline-success">Export Excel</a>
                <a href="{{ route('master-data.imports.template', $importType) }}" class="btn btn-outline-secondary">Download Template</a>
                <a href="{{ route('master-data.imports.create', $importType) }}" class="btn btn-outline-primary">Import Excel</a>
                <a href="{{ route($routePrefix.'.create') }}" class="btn btn-primary">Add Record</a>
            </div>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="card-header">
                <form class="row g-2 w-100 align-items-center" method="GET">
                    <div class="col-md-6"><input class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Search code or name"></div>
                    <div class="col-auto"><button class="btn btn-outline-primary">Search</button></div>
                    @if($search !== '')<div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.index') }}">Clear</a></div>@endif
                    <div class="col-auto ms-auto">
                        <select name="per_page" class="form-select" onchange="this.form.submit()">
                            @foreach($pageSizes as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} per page</option>@endforeach
                        </select>
                    </div>
                </form>
            </div>
            <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>SL</th><th>Code</th><th>Name</th><th>Status</th><th class="w-1"></th></tr></thead><tbody>@forelse($records as $record)<tr><td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td><td><code>{{ $record->subject_code }}</code></td><td>{{ $record->subject_name }}</td><td><span class="badge {{ $record->is_active ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route($routePrefix.'.edit', $record) }}">Edit</a></td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">No records found.</td></tr>@endforelse</tbody></table></div>
            <div class="card-footer app-table-footer"><div class="app-table-summary">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</div>{{ $records->onEachSide(1)->links() }}</div>
        </div>
    </div>
</div>
@endsection
