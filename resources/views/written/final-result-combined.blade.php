@extends('layouts.app')
@section('title','Final Written Result')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
    <div class="col"><h2 class="page-title">Final Written Result — All Qualified Candidates</h2><div class="text-secondary">Registration numbers are shown in ascending registration-number order.</div></div>
    <div class="col-auto ms-auto d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('written.index') }}">Back to Written</a><a class="btn btn-outline-primary" href="{{ route('written.final-result.combined.txt') }}">Download TXT</a><a class="btn btn-outline-success" href="{{ route('written.final-result.template') }}">Fill Result Template</a><button class="btn btn-primary" onclick="window.print()">Print</button></div>
</div></div></div>
<div class="page-body"><div class="container-xl"><div class="card"><div class="card-body">
    <div class="row g-3">
        @foreach($registrations->chunk(10) as $chunk)
            <div class="col-12 font-monospace">{{ $chunk->implode('    ') }}</div>
        @endforeach
    </div>
    <div class="border-top mt-4 pt-3 h3 mb-0">TOTAL = {{ number_format($registrations->count()) }}</div>
</div><div class="card-footer text-secondary">Finalized {{ $state->result_finalized_at?->format('d-m-Y h:i A') }} · GMT+6</div></div></div></div>
@endsection
