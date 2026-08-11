<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 10pt; color: #222; }
    h1 { font-size: 16pt; margin: 0 0 3mm; text-align: center; }
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
    .meta td { border: 0.2mm solid #d7dde5; padding: 2mm; }
    .meta .label { width: 34%; font-weight: bold; background: #f4f6f8; }
    .summary { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
    .summary th, .summary td { border: 0.2mm solid #d7dde5; padding: 2mm; text-align: left; }
    .summary th { background: #eef2f6; }
    .hash { font-size: 8pt; word-break: break-all; }
</style>
</head>
<body>
<h1>Final Choice Validation Summary</h1>

<table class="meta">
    <tr><td class="label">Exam Name</td><td>{{ $examName }}</td></tr>
    <tr><td class="label">Report Title</td><td>Final Choice Validation Summary</td></tr>
    <tr><td class="label">Validation Version</td><td>{{ $summary['validation_version'] }}</td></tr>
    <tr><td class="label">Source Version</td><td>{{ $summary['source_version'] }}</td></tr>
    <tr><td class="label">Circular Version</td><td>{{ $summary['circular_version'] }}</td></tr>
    <tr><td class="label">Status</td><td>FINALIZED</td></tr>
    <tr><td class="label">Finalized By</td><td>{{ $summary['finalized_by_name'] ?: '—' }}</td></tr>
    <tr><td class="label">Finalized At</td><td>{{ optional($summary['finalized_at'])->format('Y-m-d H:i:s') }}</td></tr>
    <tr><td class="label">Finalization Note</td><td>{{ $summary['finalization_note'] }}</td></tr>
    <tr><td class="label">Dataset Hash</td><td class="hash">{{ $summary['dataset_hash'] }}</td></tr>
</table>

<table class="summary">
    <thead><tr><th>Measure</th><th>Count</th></tr></thead>
    <tbody>
        <tr><td>Total Candidates</td><td>{{ number_format($summary['total_candidates']) }}</td></tr>
        <tr><td>Valid Candidates</td><td>{{ number_format($summary['valid_candidates']) }}</td></tr>
        <tr><td>Not Applicable</td><td>{{ number_format($summary['not_applicable_candidates']) }}</td></tr>
        <tr><td>No Valid Choice</td><td>{{ number_format($summary['zero_valid_choice_candidates']) }}</td></tr>
        <tr><td>Kept Choices</td><td>{{ number_format($summary['kept_choices']) }}</td></tr>
        <tr><td>Removed Choices</td><td>{{ number_format($summary['removed_choices']) }}</td></tr>
        <tr><td>Expanded Choices</td><td>{{ number_format($summary['expanded_choices']) }}</td></tr>
    </tbody>
</table>

<h3>Status Breakdown</h3>
<table class="summary">
    <thead><tr><th>Status</th><th>Count</th></tr></thead>
    <tbody>
        @foreach($statusBreakdown as $status => $count)
            <tr>
                <td>{{ \App\Support\ChoiceValidationStatusPresenter::resultLabel($status) }}</td>
                <td>{{ number_format($count) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div style="margin-top:5mm;font-size:8pt;color:#667085;">
Generated at {{ $generatedAt->format('Y-m-d H:i:s') }}.
This is an internal administrative report of the finalized Choice Validation dataset.
</div>
</body>
</html>
