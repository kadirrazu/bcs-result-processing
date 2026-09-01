<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing:border-box; }
    body { margin:0; font-family:'{{ $banglaFontFamily }}', DejaVu Sans, sans-serif; font-size:7.6pt; color:#101828; line-height:1.25; }
    .title { text-align:center; font-size:13pt; font-weight:bold; margin-bottom:2mm; }
    .header { width:100%; border-collapse:collapse; margin-bottom:3mm; }
    .header td { border:0; padding:0.6mm 1mm; }
    .header .label { width:18%; font-weight:bold; color:#475467; }
    .meta { width:100%; border-collapse:collapse; margin-bottom:3mm; }
    .meta th,.meta td { border:0.25mm solid #c7d0db; padding:1.2mm; text-align:center; }
    .meta th { background:#eef2f6; }
    table.rows { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.rows thead { display:table-header-group; }
    table.rows tr { page-break-inside:avoid; }
    table.rows th, table.rows td { border:0.25mm solid #98a2b3; padding:1.15mm; vertical-align:middle; overflow-wrap:break-word; }
    table.rows th { background:#eef2f6; text-align:center; font-weight:bold; }
    .c { text-align:center; }
    .bn { font-family:'{{ $banglaFontFamily }}', DejaVu Sans, sans-serif; }
    .small { font-size:6.7pt; color:#475467; }
    .total td { font-weight:bold; background:#f8fafc; }
</style>
</head>
<body>
<div class="title">{{ strtoupper($reportName) }}</div>
<table class="header">
    <tr><td class="label">Exam Title</td><td>{{ $examTitle }}</td></tr>
    <tr><td class="label">Report Name</td><td>{{ $reportName }}</td></tr>
    <tr><td class="label">Generation Date &amp; Time</td><td>{{ $generatedAt->format('d M Y, h:i:s A') }}</td></tr>
</table>
<table class="meta">
    <thead><tr><th>Seat Breakup Version</th><th>Status</th><th>Total Rows</th><th>Total Posts</th><th>MQ</th><th>CFF</th><th>EM</th><th>PHC</th></tr></thead>
    <tbody><tr>
        <td>v{{ $version->version }}</td><td>{{ strtoupper($version->status) }}</td><td>{{ number_format($version->total_rows) }}</td><td>{{ number_format($version->total_posts) }}</td><td>{{ number_format($version->mq_posts) }}</td><td>{{ number_format($version->cff_posts) }}</td><td>{{ number_format($version->em_posts) }}</td><td>{{ number_format($version->phc_posts) }}</td>
    </tr></tbody>
</table>
<table class="rows">
    <thead><tr>
        <th style="width:5%">sl</th>
        <th style="width:25%">cadre_title / sub_cadre_title</th>
        <th style="width:7%">cadre_code</th>
        <th style="width:8%">sub_cadre_code</th>
        <th style="width:8%">total_post</th>
        <th style="width:7%">mq_post</th>
        <th style="width:7%">cff_post</th>
        <th style="width:7%">em_post</th>
        <th style="width:7%">phc_post</th>
        <th style="width:19%">remarks</th>
    </tr></thead>
    <tbody>
    @foreach($rows as $row)
        @php($entry = $row->circularEntry)
        <tr>
            <td class="c">{{ $row->sl }}</td>
            <td>
                @if($entry?->cadre_name_bn_snapshot)
                    <div class="bn"><strong>{{ $entry->cadre_name_bn_snapshot }}</strong></div>
                    @if($entry?->cadre_name_snapshot)<div class="small">{{ $entry->cadre_name_snapshot }}</div>@endif
                @else
                    <strong>{{ $entry?->cadre_name_snapshot ?: '—' }}</strong>
                @endif
                @if($entry?->sub_cadre_code)
                    @if($entry?->post_name_bn_snapshot)
                        <div class="bn">{{ $entry->post_name_bn_snapshot }}</div>
                        @if($entry?->post_name_snapshot)<div class="small">{{ $entry->post_name_snapshot }}</div>@endif
                    @else
                        <div>{{ $entry?->post_name_snapshot ?: '—' }}</div>
                    @endif
                @endif
            </td>
            <td class="c">{{ $entry?->cadre_code ?? $row->cadre_code }}</td>
            <td class="c">{{ $entry?->sub_cadre_code ?? '—' }}</td>
            <td class="c">{{ number_format($row->total_post) }}</td>
            <td class="c">{{ number_format($row->mq) }}</td>
            <td class="c">{{ number_format($row->cff) }}</td>
            <td class="c">{{ number_format($row->em) }}</td>
            <td class="c">{{ number_format($row->phc) }}</td>
            <td></td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="4" class="c">TOTAL</td>
            <td class="c">{{ number_format($version->total_posts) }}</td>
            <td class="c">{{ number_format($version->mq_posts) }}</td>
            <td class="c">{{ number_format($version->cff_posts) }}</td>
            <td class="c">{{ number_format($version->em_posts) }}</td>
            <td class="c">{{ number_format($version->phc_posts) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
</body>
</html>
