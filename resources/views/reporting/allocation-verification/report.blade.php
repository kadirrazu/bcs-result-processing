@extends('layouts.app')
@section('title', $title)
@section('page-header')
<div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">{{ $examination->name }}</div><h2 class="page-title">{{ $title }}</h2>@if($cadre)<div class="text-secondary">Cadre Name: {{ $cadre['abbr'] }} — {{ $cadre['name'] }} ({{ $cadre['code'] }})</div>@endif</div><div class="col-auto ms-auto d-flex gap-2"><button class="btn btn-outline-primary" onclick="window.print()">Print</button><a class="btn btn-outline-secondary" href="{{ route('examination-reports.cadre.index') }}">Back</a></div></div>
@endsection
@section('content')
<style>
.avr-choice{display:inline-block;margin:1px 4px 1px 0;padding:1px 4px;border:1px solid #d8dee9;border-radius:3px;white-space:nowrap}
.avr-choice-allocated{color:#d63939!important;border-color:#d63939;font-weight:700}
.avr-summary{font-weight:700}
.avr-table th{vertical-align:middle!important;text-align:center!important}
.avr-table td{font-size:12px;vertical-align:top}
.avr-table th{vertical-align:middle!important;text-align:center!important}
.avr-table td:nth-child(8){min-width:260px}
.avr-table td:nth-child(9){min-width:220px}
.avr-center{vertical-align:middle!important;text-align:center!important}
.avr-allocated-cadre{color:#2fb344;font-weight:700}
.avr-allocation-cell{vertical-align:middle!important;text-align:center!important}
.avr-merit-separator{border-top:1px dashed #d8dee9;margin:4px 0}
.avr-review-cadre{color:#206bc4;font-weight:700}
.avr-review-value{font-weight:700}
.avr-merit-value{font-weight:700}
.avr-merit-list{margin-top:2px}
.avr-remark-cadre{color:#d63939;font-weight:700}
.avr-remark-bcs{color:#000;font-weight:700}
@media print{.navbar,.page-header .btn,.footer,.no-print{display:none!important}.page-wrapper{margin:0!important}.container-xl{max-width:none!important;padding:0!important}.card{border:0!important;box-shadow:none!important}.avr-table th,.avr-table td{font-size:9px;padding:3px!important}.avr-choice{border:0;padding:0;margin-right:3px}.page-break-avoid{break-inside:avoid}}
</style>
<div class="alert alert-info py-2 no-print"><strong>Confidentiality:</strong> Identity-free pre-publication verification report. Registration number, name, date of birth and other candidate identity fields are intentionally excluded.</div>
<div class="card">
<div class="card-body border-bottom page-break-avoid"><div><strong>Exam Name:</strong> {{ $examination->name }}</div><div><strong>Report Title:</strong> {{ $title }}</div>@if($cadre)<div><strong>Cadre Name:</strong> {{ $cadre['abbr'] }} — {{ $cadre['name'] }} ({{ $cadre['code'] }})</div>@endif
@if($summary)<div class="mt-2 avr-summary">Total Allocation Eligible: {{ number_format($summary['eligible']) }} &nbsp; | &nbsp; Allocated: {{ number_format($summary['allocated']) }} &nbsp; | &nbsp; Non-Allocated: {{ number_format($summary['non_allocated']) }}</div>@endif</div>
<div class="table-responsive"><table class="table table-bordered table-sm avr-table mb-0"><thead><tr><th class="text-center">Merit Position</th><th class="text-center">Category</th><th class="text-center">Written Track</th><th>Allocated Cadre</th><th>Merit Position</th><th>Bachelor Subject</th><th>PRS</th><th>Allocation-ready Choice List</th><th>Higher Choice Review</th><th>Remarks</th></tr></thead><tbody>
@forelse($rows as $row)
<tr>
<td class="avr-center fw-bold">{{ $row['merit_position'] ?? '—' }}</td>
<td class="avr-center">{{ $row['category'] ?: '—' }}</td>
<td class="avr-center">{{ $row['written_track'] ?: '—' }}</td>
<td class="avr-allocation-cell">
@if(!empty($row['allocation_abbr']))
    <span class="avr-allocated-cadre">{{ $row['allocation_abbr'] }}</span><br>
    <span>(Serial: {{ $row['allocation_serial'] }})</span>
@else
    —
@endif
</td>
<td class="{{ $row['merit_general'] === null && $row['merit_technical'] === null && empty($row['technical_cadre_merits']) ? 'avr-center' : '' }}">
@if($row['merit_general'] !== null)
    General: <span class="avr-merit-value">{{ $row['merit_general'] }}</span>
@endif
@if($row['merit_technical'] !== null)
    @if($row['merit_general'] !== null)<div class="avr-merit-separator"></div>@endif
    Technical: <span class="avr-merit-value">{{ $row['merit_technical'] }}</span>
@endif
@if(!empty($row['technical_cadre_merits']))
    @if($row['merit_general'] !== null || $row['merit_technical'] !== null)<div class="avr-merit-separator"></div>@endif
    <span>Technical Cadre Merits:</span><br>
    <span class="avr-merit-list">@foreach($row['technical_cadre_merits'] as $item){{ $item['abbr'] }} (<span class="avr-merit-value">{{ $item['position'] }}</span>)@if(!$loop->last), @endif @endforeach</span>
@endif
@if($row['merit_general'] === null && $row['merit_technical'] === null && empty($row['technical_cadre_merits']))—@endif
</td>
<td class="{{ empty($row['bachelor_code']) ? 'avr-center' : '' }}">
@if(!empty($row['bachelor_code'])){{ $row['bachelor_code'] }}<br>{{ $row['bachelor_name'] }}@else—@endif
</td>
<td class="{{ empty($row['prs_code']) ? 'avr-center' : '' }}">
@if(!empty($row['prs_code'])){{ $row['prs_code'] }}<br>{{ $row['prs_name'] }}@else—@endif
</td>
<td>
@forelse($row['choices'] as $choice)<span class="avr-choice {{ $choice['allocated']?'avr-choice-allocated':'' }}">{{ $choice['abbr'] }} ({{ $choice['code'] }})</span>@empty<span class="d-block avr-center text-secondary">—</span>@endforelse
</td>
<td class="{{ empty($row['higher_choice_review']) ? 'avr-center' : '' }}">
@if(!empty($row['higher_choice_review']))
    @foreach($row['higher_choice_review'] as $review)
        <div class="{{ !$loop->last ? 'mb-1 pb-1 border-bottom' : '' }}">
            <span class="avr-review-cadre">{{ $review['cadre'] }}</span>:
            @foreach($review['details'] as $detail)
                @php
                    $detailText = ucfirst((string) $detail);
                    $segments = preg_split('/(\d+)/', $detailText, -1, PREG_SPLIT_DELIM_CAPTURE);
                @endphp

                @foreach($segments as $segment)
                    @if(preg_match('/^\d+$/', $segment))
                        <span class="avr-review-value">{{ $segment }}</span>
                    @else
                        {{ $segment }}
                    @endif
                @endforeach

                @if(!$loop->last)
                    ;
                @else
                    .
                @endif
            @endforeach
        </div>
    @endforeach
@else
    —
@endif
</td>
<td class="{{ ($row['remarks'] ?? '—') === '—' ? 'avr-center' : '' }}">
@php
    $remark = (string) ($row['remarks'] ?? '—');
    $historical = preg_match('/^Historical Cut-off due to ([A-Z0-9_-]+) in (BCS-[A-Za-z0-9.-]+)$/', $remark, $m);
@endphp
@if($historical)
    Historical Cut-off due to <span class="avr-remark-cadre">{{ $m[1] }}</span> in <span class="avr-remark-bcs">{{ $m[2] }}</span>
@else
    {{ $remark }}
@endif
</td>
</tr>
@empty<tr><td colspan="10" class="text-center text-secondary py-4">No candidates found for this finalized report population.</td></tr>@endforelse
</tbody></table></div>
@if($summary)<div class="card-body border-top page-break-avoid avr-summary">Total Allocation Eligible: {{ number_format($summary['eligible']) }} &nbsp; | &nbsp; Allocated: {{ number_format($summary['allocated']) }} &nbsp; | &nbsp; Non-Allocated: {{ number_format($summary['non_allocated']) }}</div>@endif
</div>
@endsection
