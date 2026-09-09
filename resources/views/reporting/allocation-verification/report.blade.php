@extends('layouts.app')
@section('title', $title)
@section('page-header')
<div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">{{ $examination->name }}</div><h2 class="page-title">{{ $title }}</h2>@if($cadre)<div class="text-secondary">Cadre Name: {{ $cadre['abbr'] }} — {{ $cadre['name'] }} ({{ $cadre['code'] }})</div>@endif</div><div class="col-auto ms-auto d-flex gap-2">
@if($reportType === 'technical-cadre')
<form method="POST" action="{{ route('examination-reports.cadre.verification.technical.pdf',['cadreCode'=>$cadreCode]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
@elseif($reportType === 'general-cadre')
<form method="POST" action="{{ route('examination-reports.cadre.verification.general-cadre.pdf',['cadreCode'=>$cadreCode]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
@else
<form method="POST" action="{{ route('examination-reports.cadre.verification.pdf',['type'=>$reportType]) }}">@csrf<button class="btn btn-primary" type="submit">Export PDF</button></form>
@endif
<button class="btn btn-outline-primary" onclick="window.print()">Print</button><a class="btn btn-outline-secondary" href="{{ route('examination-reports.cadre.index') }}">Back</a></div></div>
@endsection
@section('content')
<style>
.avr-choice{display:inline-block;margin:1px 4px 1px 0;padding:1px 4px;border:1px solid #d8dee9;border-radius:3px;white-space:nowrap}
.avr-choice-allocated{color:#d63939!important;border-color:#d63939;font-weight:700}
.avr-summary{font-weight:700}
.avr-table th{vertical-align:middle!important;text-align:center!important}
.avr-table td{font-size:12px;vertical-align:top}
.avr-table th{vertical-align:middle!important;text-align:center!important}
.avr-table td:nth-child(9){min-width:300px}
.avr-table td:nth-child(10){min-width:220px}
.avr-center{vertical-align:middle!important;text-align:center!important}
.avr-allocated-cadre{color:#2fb344;font-weight:700}
.avr-basis-mq{color:#000;font-weight:700}
.avr-basis-quota{color:#206bc4;font-weight:700}
.avr-quota{color:#206bc4;font-weight:700}
.avr-non-quota{color:#000}
.avr-choice-label{font-weight:700;margin-bottom:2px}
.avr-choice-line{white-space:nowrap}
.avr-choice-separator{border-top:1px dashed #b8c2cc;margin:5px 0}
.avr-allocation-cell{vertical-align:middle!important;text-align:center!important}
.avr-merit-separator{border-top:1px dashed #d8dee9;margin:4px 0}
.avr-review-cadre{color:#206bc4;font-weight:700}
.avr-review-value{font-weight:700}
.avr-merit-value{font-weight:700}
.avr-merit-list{margin-top:2px}
.avr-remark-cadre{color:#206bc4;font-weight:700}
.avr-remark-bcs{color:#000;font-weight:700}
@page{size:legal landscape;margin:0.5in}@media print{.navbar,.page-header .btn,.footer,.no-print{display:none!important}.page-wrapper{margin:0!important}.container-xl{max-width:none!important;padding:0!important}.card{border:0!important;box-shadow:none!important}.avr-table th,.avr-table td{font-size:9px;padding:3px!important}.avr-choice{border:0;padding:0;margin-right:3px}.page-break-avoid{break-inside:avoid}}

.avr-summary{
    font-size:.82rem;
    line-height:1.45;
    font-weight:400;
}
.avr-summary strong{font-weight:700}
.avr-sum-item{white-space:nowrap}
.avr-sum-sep{color:#98a2b3;margin:0 .3rem}
.avr-sum-post{color:#475569}
.avr-sum-eligible{color:#206bc4}
.avr-sum-allocated{color:#2fb344}
.avr-sum-nonallocated{color:#f59f00}
.avr-sum-other{color:#5f3dc4}
.avr-sum-none{color:#d63939}

</style>
<div class="alert alert-info py-2 no-print"><strong>Confidentiality:</strong> Identity-free pre-publication verification report. Registration number, name, date of birth and other candidate identity fields are intentionally excluded.</div>
<div class="card">
<div class="card-body border-bottom page-break-avoid"><div><strong>Exam Name:</strong> {{ $examination->name }}</div><div><strong>Report Title:</strong> {{ $title }}</div>@if($cadre)<div><strong>Cadre Name:</strong> {{ $cadre['abbr'] }} — {{ $cadre['name'] }} ({{ $cadre['code'] }})</div>@endif
@include('reporting.allocation-verification._technical-cadre-summary', [
    'summary' => $summary,
    'cadre' => $cadre,
    'class' => 'mt-2 avr-summary',
])</div>
<div class="table-responsive"><table class="table table-bordered table-sm avr-table mb-0"><thead><tr><th>Merit Position</th><th>Category</th><th>Written Track</th><th>Allocated Cadre</th><th>Merit Position</th><th>Quota</th><th>Bachelor Subject</th><th>PRS</th><th>Choice List</th><th>Higher Choice Review</th><th>Remarks</th></tr></thead><tbody>
@forelse($rows as $row)
<tr>
<td class="avr-center fw-bold">{{ $row['merit_position'] ?? '—' }}</td>
<td class="avr-center">{{ $row['category'] ?: '—' }}</td>
<td class="avr-center">{{ $row['written_track'] ?: '—' }}</td>
<td class="avr-allocation-cell">
@if(!empty($row['allocation_abbr']))
    <span class="avr-allocated-cadre">{{ $row['allocation_abbr'] }}</span><br>
    <span>(Serial: {{ $row['allocation_serial'] }})</span><br>
    <span class="{{ ($row['allocation_basis'] ?? '') === 'MQ' ? 'avr-basis-mq' : 'avr-basis-quota' }}">{{ $row['allocation_basis'] }}</span>
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
<td class="avr-center">
@if(!empty($row['quota_labels']))
    @foreach($row['quota_labels'] as $quota)<span class="avr-quota">{{ $quota }}</span>@if(!$loop->last)<br>@endif @endforeach
@else
    <span class="avr-non-quota">Non Quota</span>
@endif
</td>
<td class="{{ empty($row['bachelor_code']) ? 'avr-center' : '' }}">
@if(!empty($row['bachelor_code'])){{ $row['bachelor_code'] }}<br>{{ $row['bachelor_name'] }}@else—@endif
</td>
<td class="{{ empty($row['prs_code']) ? 'avr-center' : '' }}">
@if(!empty($row['prs_code'])){{ $row['prs_code'] }}<br>{{ $row['prs_name'] }}@else—@endif
</td>
<td>
@if(!empty($row['historical_cutoff']))
    <div class="avr-choice-label">Validated Choice</div>
    @forelse($row['validated_choices']->chunk(5) as $choiceLine)
        <div class="avr-choice-line">@foreach($choiceLine as $choice)<span class="avr-choice">{{ $choice['abbr'] }} ({{ $choice['code'] }})</span>@endforeach</div>
    @empty
        <span class="d-block text-secondary">—</span>
    @endforelse

    <div class="avr-choice-separator"></div>
@endif

<div class="avr-choice-label">Allocation-ready Choice</div>
@forelse($row['choices']->chunk(5) as $choiceLine)
    <div class="avr-choice-line">@foreach($choiceLine as $choice)<span class="avr-choice {{ $choice['allocated']?'avr-choice-allocated':'' }}">{{ $choice['abbr'] }} ({{ $choice['code'] }})</span>@endforeach</div>
@empty
    <span class="d-block text-secondary">—</span>
@endforelse

@if(!empty($row['historical_cutoff']))
    <div class="avr-choice-separator"></div>
    <div>Historical Cut-off due to <span class="avr-remark-cadre">{{ $row['historical_cutoff']['cadre'] }}</span> in <span class="avr-remark-bcs">{{ $row['historical_cutoff']['bcs'] }}</span></div>
@endif
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
<td class="avr-center">—</td>
</tr>
@empty<tr><td colspan="11" class="text-center text-secondary py-4">No candidates found for this finalized report population.</td></tr>@endforelse
</tbody></table></div>
@if($summary)
<div class="card-body border-top page-break-avoid">
    @include('reporting.allocation-verification._technical-cadre-summary', [
        'summary' => $summary,
        'cadre' => $cadre,
        'class' => 'avr-summary',
    ])
</div>
@endif
</div>
@endsection
