<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }
    body { margin:0; font-family:'{{ $banglaFontFamily }}', DejaVu Sans, sans-serif; font-size:8.2pt; color:#101828; line-height:1.28; }
    .title { text-align:center; font-size:14pt; font-weight:bold; margin-bottom:1.5mm; }
    .header-table { width:100%; border-collapse:collapse; margin-bottom:4mm; }
    .header-table td { border:0; padding:0.7mm 1mm; }
    .label { width:22%; font-weight:bold; color:#475467; }
    .summary { width:100%; border-collapse:collapse; margin-bottom:4mm; }
    .summary th, .summary td { border:0.25mm solid #c7d0db; padding:1.5mm; text-align:center; }
    .summary th { background:#eef2f6; }
    .section { margin:4mm 0 1.5mm; font-size:10.5pt; font-weight:bold; }
    table.entries { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.entries thead { display:table-header-group; }
    table.entries tr { page-break-inside:avoid; }
    table.entries th, table.entries td { border:0.25mm solid #98a2b3; padding:1.25mm; vertical-align:top; overflow-wrap:break-word; }
    table.entries th { background:#eef2f6; text-align:center; }
    .c { text-align:center; }
    .small { font-size:7.2pt; color:#475467; }
    .total td { font-weight:bold; background:#f8fafc; }
</style>
</head>
<body>
<div class="title">FINAL CIRCULAR SUMMARY</div>
<table class="header-table">
    <tr><td class="label">Exam Name</td><td>{{ $examName }}</td></tr>
    <tr><td class="label">Report Title</td><td>Final Circular Summary</td></tr>
    <tr><td class="label">Circular Version</td><td>{{ $version }}</td></tr>
    <tr><td class="label">Status</td><td>FINALIZED</td></tr>
    <tr><td class="label">Generated At</td><td>{{ $generatedAt->format('d M Y, h:i:s A') }}</td></tr>
</table>

<table class="summary">
    <thead><tr><th>Total Entries</th><th>General Entries</th><th>Technical Entries</th><th>General Posts</th><th>Technical Posts</th><th>Total Posts</th></tr></thead>
    <tbody><tr>
        <td>{{ number_format($summary['entry_count']) }}</td>
        <td>{{ number_format($summary['general_entry_count']) }}</td>
        <td>{{ number_format($summary['technical_entry_count']) }}</td>
        <td>{{ number_format($summary['general_posts']) }}</td>
        <td>{{ number_format($summary['technical_posts']) }}</td>
        <td><strong>{{ number_format($summary['total_posts']) }}</strong></td>
    </tr></tbody>
</table>

@foreach(['GG' => 'A. General Cadres and Cadre Posts', 'TT' => 'B. Professional / Technical Cadres and Cadre Posts'] as $type => $heading)
    @php($section = $entries->filter(fn($entry) => $entry->cadre_type->value === $type && $entry->status === 'active'))
    <div class="section">{{ $heading }}</div>
    <table class="entries">
        <thead><tr>
            <th style="width:6%">Serial</th>
            <th style="width:19%">Cadre Name</th>
            <th style="width:23%">Post Name</th>
            <th style="width:8%">Code</th>
            <th style="width:8%">Posts</th>
            @if($type === 'TT')
                <th style="width:18%">Bachelor Subject Codes</th>
                <th style="width:18%">PRS Codes</th>
            @else
                <th style="width:36%">Note</th>
            @endif
        </tr></thead>
        <tbody>
        @forelse($section as $entry)
            <tr>
                <td class="c">{{ $entry->cadre_serial }}@if($entry->sub_serial !== null).{{ $entry->sub_serial }}@endif</td>
                <td>{{ $entry->cadre_name_bn_snapshot ?: $entry->cadre_name_snapshot }}<div class="small">{{ $entry->cadre_name_snapshot }}</div></td>
                <td>{{ $entry->post_name_bn_snapshot ?: $entry->post_name_snapshot ?: '—' }}<div class="small">{{ $entry->post_name_snapshot }}</div></td>
                <td class="c"><strong>{{ $entry->effective_code }}</strong></td>
                <td class="c">{{ number_format($entry->post_count) }}</td>
                @if($type === 'TT')
                    <td>{{ $entry->bachelorSubjects->pluck('subject_code')->implode(', ') ?: '—' }}</td>
                    <td>{{ $entry->prsSubjects->pluck('prs_code')->implode(', ') ?: '—' }}</td>
                @else
                    <td>{{ $entry->note ?: '—' }}</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $type === 'TT' ? 7 : 6 }}" class="c">No active entries.</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="4" class="c">Total</td>
            <td class="c">{{ number_format($section->sum('post_count')) }}</td>
            <td colspan="{{ $type === 'TT' ? 2 : 1 }}"></td>
        </tr>
        </tbody>
    </table>
@endforeach
</body>
</html>
