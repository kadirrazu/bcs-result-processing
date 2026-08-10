<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: '{{ $banglaFontFamily }}', DejaVu Sans, sans-serif;
        font-size: 8.2pt;
        color: #101828;
        line-height: 1.28;
    }
    .bn { font-family: '{{ $banglaFontFamily }}', DejaVu Sans, sans-serif; }
    .report-head { width: 100%; margin: 0 0 4mm; border-collapse: collapse; }
    .report-head td { border: 0; padding: .6mm 0; vertical-align: top; }
    .report-head .label { width: 17%; font-weight: bold; color: #344054; }
    .report-head .value { width: 83%; font-weight: bold; color: #101828; }
    .report-title { text-align: center; margin: 0 0 3mm; font-size: 14pt; font-weight: bold; }
    .generated { text-align: center; color: #667085; margin-bottom: 4mm; font-size: 8pt; }
    .section { margin-top: 4mm; margin-bottom: 1.5mm; font-size: 10.5pt; font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.data thead { display: table-header-group; }
    table.data tr { page-break-inside: avoid; }
    table.data th, table.data td {
        border: .25mm solid #98a2b3;
        padding: 1.4mm;
        vertical-align: top;
        overflow-wrap: break-word;
    }
    table.data th { background: #eef2f6; text-align: center; font-weight: bold; }
    .c { text-align: center; }
    .small { font-size: 7.3pt; color: #475467; }
    .inactive { color: #98a2b3; }
    .total td { font-weight: bold; background: #f8fafc; }
</style>
</head>
<body>

<div class="report-title">{{ $reportTitle }}</div>
<table class="report-head">
    <tr>
        <td class="label">Exam Name:</td>
        <td class="value">{{ $examName }}</td>
    </tr>
    <tr>
        <td class="label">Report Title:</td>
        <td class="value">{{ $reportTitle }}</td>
    </tr>
    <tr>
        <td class="label">Circular Version:</td>
        <td class="value">{{ $version }}</td>
    </tr>
</table>
<div class="generated">
    Generated: {{ $generatedAt->format('d M Y, h:i:s A') }} | This preview must be confirmed before finalization.
</div>

@foreach([
    'GG' => 'A. General Cadres and Cadre Posts',
    'TT' => 'B. Professional / Technical Cadres and Cadre Posts',
] as $type => $heading)
    @php($section = $entries->filter(fn($entry) => $entry->cadre_type->value === $type))

    <div class="section">{{ $heading }}</div>

    <table class="data">
        <thead>
        <tr>
            <th style="width:6%">Serial</th>
            <th style="width:17%">Cadre Name</th>
            <th style="width:21%">Post Name</th>
            <th style="width:8%">Code</th>
            <th style="width:8%">Vacant Posts</th>
            @if($type === 'TT')
                <th style="width:20%">Educational Subject Codes</th>
                <th style="width:20%">Written Post-Related Subject Codes</th>
            @else
                <th style="width:40%">Status / Note</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @forelse($section as $entry)
            <tr @class(['inactive' => $entry->status !== 'active'])>
                <td class="c">
                    {{ $entry->cadre_serial }}@if($entry->sub_serial).{{ $entry->sub_serial }}@endif
                </td>
                <td>
                    @if($entry->cadre_name_bn_snapshot)
                        <div class="bn">{{ $entry->cadre_name_bn_snapshot }}</div>
                    @endif
                    <div class="small">{{ $entry->cadre_name_snapshot }}</div>
                </td>
                <td>
                    @if($entry->post_name_bn_snapshot)
                        <div class="bn">{{ $entry->post_name_bn_snapshot }}</div>
                    @endif
                    @if($entry->post_name_snapshot)
                        <div class="small">{{ $entry->post_name_snapshot }}</div>
                    @endif
                    @if($entry->note)
                        <div class="small">{{ $entry->note }}</div>
                    @endif
                </td>
                <td class="c"><strong>{{ $entry->effective_code }}</strong></td>
                <td class="c">{{ number_format($entry->post_count) }}</td>
                @if($type === 'TT')
                    <td>
                        @foreach($entry->bachelorSubjects as $subject)
                            <div>{{ $subject->subject_code }}</div>
                        @endforeach
                    </td>
                    <td>
                        @foreach($entry->prsSubjects as $subject)
                            <div>{{ $subject->prs_code }}</div>
                        @endforeach
                    </td>
                @else
                    <td>
                        {{ strtoupper($entry->status) }}@if($entry->note) - {{ $entry->note }}@endif
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $type === 'TT' ? 7 : 6 }}" class="c">No entries.</td>
            </tr>
        @endforelse

        <tr class="total">
            <td colspan="4" class="c">Total =</td>
            <td class="c">{{ number_format($section->where('status', 'active')->sum('post_count')) }}</td>
            <td colspan="{{ $type === 'TT' ? 2 : 1 }}"></td>
        </tr>
        </tbody>
    </table>
@endforeach

</body>
</html>
