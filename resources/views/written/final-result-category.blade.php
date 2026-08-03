@extends('layouts.app')
@section('title','Category-wise Final Written Result')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
    <div class="col"><h2 class="page-title">Final Written Result — Category-wise</h2><div class="text-secondary">GG includes GG + GN, TT includes TT + T, and GT contains only candidates qualified in both tracks.</div></div>
    <div class="col-auto ms-auto d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('written.index') }}">Back to Written</a><a class="btn btn-outline-primary" href="{{ route('written.final-result.category.txt') }}">Download TXT</a><a class="btn btn-outline-success" href="{{ route('written.exports.xlsx', ['scope'=>'qualified','order'=>'reg','direction'=>'asc']) }}">Download XLSX</a><a class="btn btn-outline-success" href="{{ route('written.final-result.template') }}">Fill Result Template</a><button class="btn btn-primary" onclick="window.print()">Print</button></div>
</div></div></div>
<div class="page-body"><div class="container-xl">
@foreach($groups as $category => $registrations)
<div class="card mb-3"><div class="card-header"><h3 class="card-title">{{ $category }}</h3></div><div class="card-body">
    <div class="row g-3">@foreach($registrations->chunk(10) as $chunk)<div class="col-12 font-monospace">{{ $chunk->implode('    ') }}</div>@endforeach</div>
    <div class="border-top mt-4 pt-3 h3 mb-0">TOTAL ({{ $category }}) = {{ number_format($registrations->count()) }}</div>
</div></div>
@endforeach
</div></div>
@endsection
