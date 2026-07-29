@extends('layouts.app')
@section('title', 'Cadre Masters')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Master Data</div>
                <h2 class="page-title">Cadre Masters</h2>
            </div>
            <div class="col-auto ms-auto d-flex flex-wrap gap-2">
                <a href="{{ route('master-data.exports.pdf', 'cadre-masters') }}" class="btn btn-outline-danger">Export PDF</a>
                <a href="{{ route('master-data.exports.excel', 'cadre-masters') }}" class="btn btn-outline-success">Export Excel</a>
                <a href="{{ route('master-data.imports.template', 'cadre-masters') }}" class="btn btn-outline-secondary">Download Template</a>
                <a href="{{ route('master-data.imports.create', 'cadre-masters') }}" class="btn btn-outline-primary">Import Excel</a>
                <a href="{{ route('cadre-masters.create') }}" class="btn btn-primary">Add Cadre</a>
            </div>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="card-header">
                <form class="row g-2 w-100 align-items-center" method="GET">
                    <div class="col-md-6"><input class="form-control" name="search" value="{{ $search }}" placeholder="Search code, abbreviation or title"></div>
                    <div class="col-auto"><button class="btn btn-outline-primary">Search</button></div>
                    @if($search !== '')<div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('cadre-masters.index') }}">Clear</a></div>@endif
                    <div class="col-auto ms-auto">
                        <select name="per_page" class="form-select" onchange="this.form.submit()">
                            @foreach($pageSizes as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} per page</option>@endforeach
                        </select>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>SL</th><th>Order</th><th>Code</th><th>Abbreviation</th><th>English title</th><th>বাংলা নাম</th><th>Type</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($records as $record)
                        <tr><td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td><td>{{ $record->display_order }}</td><td>{{ $record->cadre_code }}</td><td><code>{{ $record->cadre_abbr }}</code></td><td>{{ $record->cadre_title }}</td><td>{{ $record->cadre_title_bn }}</td><td>{{ $record->cadre_type->value }}</td><td><span class="badge {{ $record->is_active ? 'bg-success-lt' : 'bg-secondary-lt' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('cadre-masters.edit', $record) }}">Edit</a></td></tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-secondary py-4">No cadres found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer app-table-footer">
                <div class="app-table-summary">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</div>
                {{ $records->onEachSide(1)->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
