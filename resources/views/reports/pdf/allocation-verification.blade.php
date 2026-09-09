<!doctype html>
<html>
<head>
<meta charset="utf-8">
@include('reporting.allocation-verification._verification-table-style')
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

@include('reports.pdf._quota-summary', [
    'quotaSummary' => $quotaSummary ?? null,
])
</div>

@include('reporting.allocation-verification._verification-table', [
    'rows' => $rows,
    'reportType' => ($quotaSummary ?? null) ? 'quota' : ($reportType ?? ''),
    'interactive' => false,
])


@include('reports.pdf._technical-cadre-summary', [
    'summary' => $summary,
    'cadre' => $cadre,
    'footer' => true,
])

@if($quotaSummary ?? null)
<div style="margin-top:2mm">
@include('reports.pdf._quota-summary', [
    'quotaSummary' => $quotaSummary,
])
</div>
@endif
</body>
</html>
