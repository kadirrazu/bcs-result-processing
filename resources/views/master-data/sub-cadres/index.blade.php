@extends('layouts.app')

@section('title', 'Sub Cadre Master')

@section('content')
<div class="page-header">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Sub Cadre Master</h2>
                <div class="text-secondary">Reusable child codes. Cadre name is inherited from the parent; post name is child-specific.</div>
            </div>
            <div class="col-auto ms-auto">
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('master-data.exports.pdf', 'cadre-sub-masters') }}">Export PDF</a>
                    <a class="btn btn-outline-secondary" href="{{ route('master-data.exports.excel', 'cadre-sub-masters') }}">Export Excel</a>
                    <a class="btn btn-outline-primary" href="{{ route('master-data.imports.create', 'cadre-sub-masters') }}">Import Excel</a>
                    <a class="btn btn-primary" href="{{ route('cadre-sub-masters.create') }}">Add Sub Cadre</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2">
                    <div class="col-md-5">
                        <input class="form-control" name="search" value="{{ $search }}" placeholder="Sub code, parent code, abbreviation or post name">
                    </div>
                    <div class="col-auto"><button class="btn btn-primary">Search</button></div>
                    <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('cadre-sub-masters.index') }}">Clear</a></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Sub cadre catalogue</h3>
                    <div class="text-secondary small">
                        Displaying {{ number_format($records->firstItem() ?? 0) }} to {{ number_format($records->lastItem() ?? 0) }} of {{ number_format($records->total()) }}
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>SL</th><th>SUB CODE</th><th>PARENT</th><th>CADRE NAME</th><th>POST NAME</th><th>ORDER</th><th>STATUS</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
                            <td class="fw-semibold">{{ $record->sub_cadre_code }}</td>
                            <td>{{ $record->parentCadre->cadre_code }}</td>
                            <td>
                                <div>{{ $record->parentCadre->cadre_name }}</div>
                                <div class="text-secondary small">{{ $record->parentCadre->cadre_name_bn }}</div>
                            </td>
                            <td>
                                <div>{{ $record->post_name }}</div>
                                <div class="text-secondary small">{{ $record->post_name_bn }}</div>
                            </td>
                            <td>{{ $record->display_order }}</td>
                            <td><span class="badge {{ $record->is_active ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' }}">{{ $record->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('cadre-sub-masters.edit', $record) }}">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-4">No Sub Cadre Master records.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($records->hasPages())
                <div class="card-footer">{{ $records->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
