<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:7.5pt;color:#182433}
.report-head{margin-bottom:4mm;line-height:1.35}.report-head div{margin-bottom:.7mm}.summary{font-size:7.4pt;line-height:1.35;font-weight:400;margin-top:1.5mm}.summary-footer{margin-top:2mm}
table{width:100%;border-collapse:collapse}th,td{border:.2mm solid #b8c2cc;padding:1.2mm 1mm;vertical-align:top}
th{text-align:center;vertical-align:middle;font-weight:700;background:#f3f5f7}.center{text-align:center;vertical-align:middle}
.allocated-cadre{color:#2fb344;font-weight:700}.basis-mq{color:#000;font-weight:700}
.basis-quota,.quota,.review-cadre,.historical-cadre{color:#206bc4;font-weight:700}
.non-quota,.historical-bcs{color:#000}.historical-bcs{font-weight:700}.merit-value,.review-value{font-weight:700}
.separator{border-top:.2mm dashed #b8c2cc;margin:1mm 0}.choice-label{font-weight:700;margin-bottom:.4mm}.choice-line{white-space:nowrap}
.choice{display:inline-block;margin:0 .8mm .4mm 0;padding:0 .5mm;border:.2mm solid #d8dee9;white-space:nowrap}
.choice-allocated{color:#d63939;border-color:#d63939;font-weight:700}.review-item{margin-bottom:.8mm}
.sum-item{white-space:nowrap}
.sum-item strong{font-weight:700}
.sum-sep{color:#98a2b3;margin:0 .8mm}
.sum-post{color:#475569}
.sum-eligible{color:#206bc4}
.sum-allocated{color:#2fb344}
.sum-nonallocated{color:#f59f00}
.sum-other{color:#5f3dc4}
.sum-none{color:#d63939}
</style>
</head>
<body>
<div class="report-head">
<div><strong>Exam Name:</strong> {{ $examinationName }}</div>
<div><strong>Report Title:</strong> {{ $title }}</div>
@if($cadre)<div><strong>Cadre Name:</strong> {{ $cadre['abbr'] }} - {{ $cadre['name'] }} ({{ $cadre['code'] }})</div>@endif
@include('reports.pdf._technical-cadre-summary', [
    'summary' => $summary,
    'cadre' => $cadre,
    'footer' => false,
])
</div>

<table>
<thead><tr><th>Merit Position</th><th>Category</th><th>Written Track</th><th>Allocated Cadre</th><th>Merit Position</th><th>Quota</th><th>Bachelor Subject</th><th>PRS</th><th>Choice List</th><th>Higher Choice Review</th><th>Remarks</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
<td class="center"><strong>{{ $row['merit_position'] ?? '-' }}</strong></td>
<td class="center">{{ $row['category'] ?: '-' }}</td>
<td class="center">{{ $row['written_track'] ?: '-' }}</td>
<td class="center">@if(!empty($row['allocation_abbr']))<span class="allocated-cadre">{{ $row['allocation_abbr'] }}</span><br>(Serial: {{ $row['allocation_serial'] }})<br><span class="{{ ($row['allocation_basis'] ?? '') === 'MQ' ? 'basis-mq' : 'basis-quota' }}">{{ $row['allocation_basis'] }}</span>@else-@endif</td>
<td>
@if($row['merit_general'] !== null)General: <span class="merit-value">{{ $row['merit_general'] }}</span>@endif
@if($row['merit_technical'] !== null)@if($row['merit_general'] !== null)<div class="separator"></div>@endif Technical: <span class="merit-value">{{ $row['merit_technical'] }}</span>@endif
@if(!empty($row['technical_cadre_merits']))@if($row['merit_general'] !== null || $row['merit_technical'] !== null)<div class="separator"></div>@endif Technical Cadre Merits:<br>@foreach($row['technical_cadre_merits'] as $item){{ $item['abbr'] }} (<span class="merit-value">{{ $item['position'] }}</span>)@if(!$loop->last), @endif @endforeach @endif
@if($row['merit_general'] === null && $row['merit_technical'] === null && empty($row['technical_cadre_merits']))-@endif
</td>
<td class="center">@if(!empty($row['quota_labels']))@foreach($row['quota_labels'] as $quota)<span class="quota">{{ $quota }}</span>@if(!$loop->last)<br>@endif @endforeach @else<span class="non-quota">Non Quota</span>@endif</td>
<td>@if(!empty($row['bachelor_code'])){{ $row['bachelor_code'] }}<br>{{ $row['bachelor_name'] }}@else<div class="center">-</div>@endif</td>
<td>@if(!empty($row['prs_code'])){{ $row['prs_code'] }}<br>{{ $row['prs_name'] }}@else<div class="center">-</div>@endif</td>
<td>
@if(!empty($row['historical_cutoff']))
<div class="choice-label">Validated Choice</div>
@forelse($row['validated_choices']->chunk(5) as $choiceLine)<div class="choice-line">@foreach($choiceLine as $choice)<span class="choice">{{ $choice['abbr'] }} ({{ $choice['code'] }})</span>@endforeach</div>@empty-@endforelse
<div class="separator"></div>
@endif
<div class="choice-label">Allocation-ready Choice</div>
@forelse($row['choices']->chunk(5) as $choiceLine)<div class="choice-line">@foreach($choiceLine as $choice)<span class="choice {{ $choice['allocated'] ? 'choice-allocated' : '' }}">{{ $choice['abbr'] }} ({{ $choice['code'] }})</span>@endforeach</div>@empty-@endforelse
@if(!empty($row['historical_cutoff']))<div class="separator"></div>Historical Cut-off due to <span class="historical-cadre">{{ $row['historical_cutoff']['cadre'] }}</span> in <span class="historical-bcs">{{ $row['historical_cutoff']['bcs'] }}</span>@endif
</td>
<td>
@if(!empty($row['higher_choice_review']))
@foreach($row['higher_choice_review'] as $review)
<div class="review-item"><span class="review-cadre">{{ $review['cadre'] }}</span>:
@foreach($review['details'] as $detail)
@php($segments=preg_split('/(\d+)/', ucfirst((string)$detail), -1, PREG_SPLIT_DELIM_CAPTURE))
@foreach($segments as $segment)
@if(preg_match('/^\d+$/',$segment))<span class="review-value">{{ $segment }}</span>@else{{ $segment }}@endif
@endforeach
@if(!$loop->last)
;
@else
.
@endif
@endforeach
</div>
@endforeach
@else<div class="center">-</div>@endif
</td>
<td class="center">-</td>
</tr>
@empty<tr><td colspan="11" class="center">No candidates found for this finalized report population.</td></tr>@endforelse
</tbody>
</table>

@include('reports.pdf._technical-cadre-summary', [
    'summary' => $summary,
    'cadre' => $cadre,
    'footer' => true,
])
</body>
</html>
