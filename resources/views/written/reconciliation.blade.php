@extends('layouts.app')
@section('title','Written Reconciliation')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Written Eligible / Appeared / Absent Reconciliation</h2><div class="text-secondary">Eligible population comes from finalized Preliminary PASS results.</div></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('written.index') }}">Back to Written</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(!$report)<div class="alert alert-warning">No reconciliation has been generated yet.</div>@else
@php
    $summary = (array) $report->summary;
@endphp
<div class="card"><div class="card-header"><h3 class="card-title">Reconciliation Summary</h3><div class="card-actions text-secondary">Generated {{ $report->generated_at?->format('d-m-Y h:i A') }}</div></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Metric</th><th class="text-end fw-bold">Total</th><th class="text-end">GG</th><th class="text-end">TT</th><th class="text-end">GT</th></tr></thead><tbody>
@foreach(['eligible'=>'Eligible (Preliminary PASS)','appeared'=>'Appeared','completely_absent'=>'Completely Absent','mandatory_subject_absent'=>'Appeared but mandatory subject ABS/AAA'] as $key=>$label)
@php
    $m = (array) ($summary[$key] ?? []);
@endphp
<tr><td>{{ $label }}</td><td class="text-end fw-bold">{{ number_format((int)($m['total']??0)) }}</td><td class="text-end">{{ number_format((int)($m['GG']??0)) }}</td><td class="text-end">{{ number_format((int)($m['TT']??0)) }}</td><td class="text-end">{{ number_format((int)($m['GT']??0)) }}</td></tr>
@endforeach
</tbody></table></div>
<div class="card-footer text-secondary small">
@php
    $missing = (array) ($summary['missing_from_file'] ?? []);
    $allAbs = (array) ($summary['all_applicable_subjects_absent'] ?? []);
@endphp
Completely Absent includes {{ number_format((int)($missing['total']??0)) }} eligible candidate(s) with no Written row and {{ number_format((int)($allAbs['total']??0)) }} candidate(s) whose every applicable mark is ABS/AAA.
</div></div>
@endif
</div></div>
@endsection
