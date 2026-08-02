@extends('layouts.app')
@section('title','Written Results')
@section('content')
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><h2 class="page-title">Merged Written Results</h2><div class="text-secondary">Warning candidates are listed first. After W3 processing, track PASS/FAIL, totals, fail reasons and written_qualified_track are shown here.</div></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('written.index') }}">Back to Written</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if($state->is_stale)<div class="alert alert-warning"><strong>Written processing is stale.</strong> {{ $state->stale_reason }} Derived PASS/FAIL, totals and qualified-track values must be regenerated before downstream use.</div>@endif
<div class="card mb-3"><div class="card-body"><form method="get" class="row g-2"><div class="col-md-2"><label class="form-label">Validation</label><select class="form-select" name="validation"><option value="all">All</option><option value="warning" @selected($filters['validation']==='warning')>Warning</option><option value="valid" @selected($filters['validation']==='valid')>Valid</option></select></div><div class="col-md-2"><label class="form-label">Processing Status</label><select class="form-select" name="status"><option value="all">All</option>@foreach(['active'=>'Active','cancelled'=>'Cancelled','withheld'=>'Withheld','expelled'=>'Expelled'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? 'all')===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">REG / USER</label><input class="form-control" name="search" value="{{ $filters['search'] }}"></div><div class="col-md-3"><label class="form-check mt-4"><input type="checkbox" class="form-check-input" name="high_mark" value="1" @checked($filters['highMark'])><span class="form-check-label">High-mark review only</span></label></div><div class="col-md-auto align-self-end"><button class="btn btn-primary">Filter</button></div><div class="col-md-auto align-self-end"><a class="btn btn-outline-secondary" href="{{ route('written.results') }}">Reset</a></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>REG</th><th>USER</th><th>Category</th><th>PRS Code</th><th>Status</th><th>Validation</th><th>Source Note</th><th>G Result</th><th>T Result</th><th>Qualified Track</th><th>G Counted</th><th>T Counted</th><th>Fail Reasons</th><th>High-mark / warnings</th><th class="w-1"></th></tr></thead><tbody>
@forelse($rows as $row)
@php
    $processingStatus = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
    $validationStatus = $row->validation_status instanceof \BackedEnum
        ? $row->validation_status->value
        : (string) $row->validation_status;

    $categoryValue = $row->cadre_category instanceof \BackedEnum
        ? $row->cadre_category->value
        : $row->cadre_category;
    $categoryLabel = [1 => 'GG', 2 => 'TT', 3 => 'GT'][$categoryValue] ?? $categoryValue;

    $generalStatus = $row->general_result_status instanceof \BackedEnum
        ? $row->general_result_status->value
        : (string) ($row->general_result_status ?? '');
    $technicalStatus = $row->technical_result_status instanceof \BackedEnum
        ? $row->technical_result_status->value
        : (string) ($row->technical_result_status ?? '');
    $qualifiedTrack = $row->written_qualified_track instanceof \BackedEnum
        ? $row->written_qualified_track->value
        : (string) ($row->written_qualified_track ?? '');

    $failReasons = array_merge(
        (array) ($row->general_fail_reasons ?? []),
        (array) ($row->technical_fail_reasons ?? [])
    );
    $failReasonText = collect($failReasons)
        ->map(fn ($reason) => is_array($reason) ? ($reason['code'] ?? json_encode($reason)) : (string) $reason)
        ->implode(' | ');

    $messages = (array) data_get($row->processing_flags, 'validation_warnings', []);
    foreach ($row->marks as $mark) {
        foreach (($mark->warning_codes ?? []) as $message) {
            $messages[] = $message;
        }
    }
    $warningText = implode(' | ', array_values(array_unique($messages)));
@endphp
<tr class="{{ $validationStatus === 'warning' ? 'table-warning' : '' }}">
    <td>{{ $row->reg }}</td>
    <td>{{ $row->user_id }}</td>
    <td>{{ $categoryLabel }}</td>
    <td>{{ $row->prs_code ?? '—' }}</td>
    <td>{{ ucfirst($processingStatus) }}</td>
    <td>{{ strtoupper($validationStatus) }}</td>
    <td>{{ $row->data_source_note ?? '—' }}</td>
    <td>{{ $generalStatus !== '' ? strtoupper($generalStatus) : '—' }}</td>
    <td>{{ $technicalStatus !== '' ? strtoupper($technicalStatus) : '—' }}</td>
    <td><strong>{{ $qualifiedTrack !== '' ? $qualifiedTrack : '—' }}</strong></td>
    <td>{{ $row->general_counted_total !== null ? number_format((float) $row->general_counted_total, 2) : '—' }}</td>
    <td>{{ $row->technical_counted_total !== null ? number_format((float) $row->technical_counted_total, 2) : '—' }}</td>
    <td class="small">{{ $failReasonText !== '' ? $failReasonText : '—' }}</td>
    <td class="small">{{ $warningText !== '' ? $warningText : '—' }}</td>
    <td><div class="btn-list flex-nowrap"><a href="{{ route('written.results.show',$row) }}" class="btn btn-sm btn-outline-primary">View</a><a href="{{ route('written.results.edit',$row) }}" class="btn btn-sm btn-outline-secondary">Edit</a></div></td>
</tr>
@empty<tr><td colspan="15" class="text-center text-secondary py-4">No Written result rows.</td></tr>@endforelse
</tbody></table></div>@if($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
</div></div>
@endsection
