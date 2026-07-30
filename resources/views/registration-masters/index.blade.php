@extends('layouts.app')
@section('title', $definition['title'])
@section('content')
<div class="page-header">
    <div class="container-xl">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle">Central Master Data</div>
                <h2 class="page-title">{{ $definition['title'] }}</h2>
            </div>
            <div class="col-auto d-flex gap-2">
                @if(in_array($type, ['divisions', 'districts', 'universities'], true))
                    <a class="btn btn-outline-secondary" href="{{ route('master-data.imports.template', $type) }}">Download Template</a>
                    <a class="btn btn-outline-primary" href="{{ route('master-data.imports.create', $type) }}">Import Excel</a>
                @endif
                <a class="btn btn-primary" href="{{ route('registration-masters.create', $type) }}">Add Record</a>
            </div>
        </div>
    </div>
</div>
<div class="page-body">
    <div class="container-xl">
        <div class="card">
            <div class="card-header"><form><input class="form-control" name="search" value="{{ $search }}" placeholder="Search code or name"></form></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr>@foreach($definition['fields'] as $field)<th>{{ str($field)->headline() }}</th>@endforeach<th>Status</th><th></th></tr></thead>
                    <tbody>@foreach($records as $record)<tr>@foreach($definition['fields'] as $field)<td>{{ $record->$field }}</td>@endforeach<td>{{ $record->is_active ? 'Active' : 'Inactive' }}</td><td><a href="{{ route('registration-masters.edit', [$type, $record]) }}">Edit</a></td></tr>@endforeach</tbody>
                </table>
            </div>
            <div class="card-footer">{{ $records->links() }}</div>
        </div>
    </div>
</div>
@endsection
