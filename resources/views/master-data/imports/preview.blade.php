@extends('layouts.app')
@section('title', 'Import Preview')
@section('content')
@php
    $validCount = collect($rows)->where('valid', true)->count();
    $invalidCount = count($rows) - $validCount;
    $existingCount = collect($rows)->where('valid', true)->where('exists', true)->count();
    $newCount = collect($rows)->where('valid', true)->where('exists', false)->count();
@endphp
<div class="page-header d-print-none"><div class="container-xl"><div class="row align-items-center"><div class="col"><div class="page-pretitle">Master Data</div><h2 class="page-title">{{ $definition->label() }} Import Preview</h2></div></div></div></div>
<div class="page-body"><div class="container-xl">
    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Total rows</div><div class="h2 mb-0">{{ count($rows) }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">New</div><div class="h2 mb-0">{{ $newCount }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Existing</div><div class="h2 mb-0">{{ $existingCount }}</div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card card-sm"><div class="card-body"><div class="text-secondary">Invalid</div><div class="h2 mb-0">{{ $invalidCount }}</div></div></div></div>
    </div>
    <div class="card">
        @if($invalidCount > 0)<div class="alert alert-warning m-3 mb-0">Invalid rows will not be imported. Correct the spreadsheet and upload again when those rows are required.</div>@endif
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Row</th>@foreach($definition->headers() as $header)<th>{{ $header }}</th>@endforeach<th>State</th></tr></thead><tbody>@foreach($rows as $row)<tr class="{{ $row['valid'] ? '' : 'table-danger' }}"><td>{{ $row['row_number'] }}</td>@foreach($definition->headers() as $header)<td>{{ is_bool($row['data'][$header] ?? null) ? (($row['data'][$header] ?? false) ? '1' : '0') : ($row['data'][$header] ?? '') }}</td>@endforeach<td>@if(!$row['valid'])<span class="badge bg-danger-lt">Invalid</span><div class="small text-danger mt-1">{{ implode(' ', $row['errors']) }}</div>@elseif($row['exists'])<span class="badge bg-warning-lt">Existing</span>@else<span class="badge bg-success-lt">New</span>@endif</td></tr>@endforeach</tbody></table></div>
        <div class="card-footer d-flex justify-content-between"><a class="btn btn-link" href="{{ route('master-data.imports.create', $definition->key) }}">Upload another file</a><form method="POST" action="{{ route('master-data.imports.store', $definition->key) }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn btn-primary" @disabled($validCount === 0) onclick="return confirm('Confirm this import?')">Confirm Import</button></form></div>
    </div>
</div></div>
@endsection
