@extends('layouts.app')
@section('title', 'Preliminary Reconciliation')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title">Present / Absent Reconciliation</h2><div class="text-secondary">Report #{{ $report->id }} · Generated {{ $report->generated_at?->format('d-m-Y h:i A') }}</div></div><div class="col-auto ms-auto"><a href="{{ route('preliminary.index') }}" class="btn btn-outline-secondary">Back</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row row-cards mb-3">
@foreach([
'Active Registrations'=>$summary['active_registered'], 'Imported Rows'=>$summary['imported_rows'], 'Present with Mark'=>$summary['present_with_mark'],
'Cancelled with Reason'=>$summary['cancelled_with_reason'], 'Cancelled without Reason'=>$summary['cancelled_without_reason'], 'Absent'=>$summary['absent']
] as $label=>$value)
<div class="col-sm-6 col-lg-4"><div class="card card-sm"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format($value) }}</div></div></div></div>
@endforeach
</div>
<div class="card mb-3"><div class="card-header"><h3 class="card-title">Category Breakdown</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Group</th><th class="text-end">GG</th><th class="text-end">TT</th><th class="text-end">GT</th><th class="text-end">Total</th></tr></thead><tbody>
@foreach([
'Active Registered'=>'active_registered','Present with Mark'=>'present_with_mark','Present + Status Text'=>'present_with_status_text','Cancelled with Reason'=>'cancelled_with_reason','Cancelled without Reason'=>'cancelled_without_reason','Absent'=>'absent'
] as $label=>$key)
@php($row=$summary['category'][$key])
<tr><td>{{ $label }}</td><td class="text-end">{{ number_format($row['GG']) }}</td><td class="text-end">{{ number_format($row['TT']) }}</td><td class="text-end">{{ number_format($row['GT']) }}</td><td class="text-end fw-bold">{{ number_format($row['total']) }}</td></tr>
@endforeach
</tbody></table></div></div>
<div class="card"><div class="card-header"><h3 class="card-title">Action Reports</h3></div><div class="card-body d-flex flex-wrap gap-2">
<a class="btn btn-outline-primary" href="{{ route('preliminary.reconciliation.csv', [$report,'present_status']) }}">Present + Status Text CSV</a>
<a class="btn btn-outline-primary" href="{{ route('preliminary.reconciliation.csv', [$report,'cancelled_reason']) }}">Cancelled with Reason CSV</a>
<a class="btn btn-outline-warning" href="{{ route('preliminary.reconciliation.csv', [$report,'cancelled_no_reason']) }}">Cancelled without Reason CSV</a>
<a class="btn btn-outline-danger" href="{{ route('preliminary.reconciliation.csv', [$report,'absent']) }}">Absent CSV</a>
</div></div>
</div></div>
@endsection
