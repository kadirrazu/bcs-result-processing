<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing:border-box; }
    body {
        margin:0;
        font-family:'{{ $banglaFontFamily }}', DejaVu Sans, sans-serif;
        font-size:7.2pt;
        color:#101828;
        line-height:1.18;
    }
    table.meta {
        width:100%;
        border-collapse:collapse;
        margin:0 0 3mm 0;
    }
    table.meta th, table.meta td {
        border:0.22mm solid #c7d0db;
        padding:1.1mm .8mm;
        text-align:center;
        vertical-align:middle;
    }
    table.meta th { background:#eef2f6; font-weight:bold; }

    table.rows {
        width:100%;
        border-collapse:collapse;
        table-layout:fixed;
        margin:0 auto;
    }
    table.rows thead { display:table-header-group; }
    table.rows tr { page-break-inside:avoid; }
    table.rows th, table.rows td {
        border:0.22mm solid #98a2b3;
        padding:1.55mm .80mm;
        vertical-align:middle;
        overflow-wrap:break-word;
    }
    table.rows th {
        background:#eef2f6;
        text-align:center;
        font-weight:bold;
        font-size:6.8pt;
    }
    .c { text-align:center; }
    .bn {
        font-family:'{{ $banglaFontFamily }}', DejaVu Sans, sans-serif;
        font-size:7.4pt;
        line-height:1.28;
    }
    .bn-title {
        font-family:'{{ $banglaFontFamily }}', DejaVu Sans, sans-serif;
        font-size:7.9pt;
        font-weight:normal;
        line-height:1.32;
    }
    .en { font-size:6.3pt; color:#475467; line-height:1.15; }
    .total td { font-weight:bold; background:#f8fafc; }

    .w-sl      { width:8mm; }
    .w-title   { width:55mm; }
    .w-code    { width:17mm; }
    .w-subcode { width:18mm; }
    .w-post    { width:16mm; }
    .w-quota   { width:14mm; }
    .w-remarks { width:23mm; }
</style>
</head>
<body>
<table class="meta">
    <thead>
    <tr>
        <th>Seat Breakup Version</th>
        <th>Status</th>
        <th>Total Rows</th>
        <th>Total Posts</th>
        <th>MQ</th>
        <th>CFF</th>
        <th>EM</th>
        <th>PHC</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>v{{ $version->version }}</td>
        <td>{{ strtoupper($version->status) }}</td>
        <td>{{ number_format($version->total_rows) }}</td>
        <td>{{ number_format($version->total_posts) }}</td>
        <td>{{ number_format($version->mq_posts) }}</td>
        <td>{{ number_format($version->cff_posts) }}</td>
        <td>{{ number_format($version->em_posts) }}</td>
        <td>{{ number_format($version->phc_posts) }}</td>
    </tr>
    </tbody>
</table>

<table class="rows">
    <colgroup>
        <col class="w-sl">
        <col class="w-title">
        <col class="w-code">
        <col class="w-subcode">
        <col class="w-post">
        <col class="w-quota">
        <col class="w-quota">
        <col class="w-quota">
        <col class="w-quota">
        <col class="w-remarks">
    </colgroup>
    <thead>
    <tr>
        <th>SL</th>
        <th>CADRE TITLE / SUB CADRE TITLE</th>
        <th>CADRE CODE</th>
        <th>SUB CADRE CODE</th>
        <th>TOTAL POST</th>
        <th>MQ POST</th>
        <th>CFF POST</th>
        <th>EM POST</th>
        <th>PHC POST</th>
        <th>REMARKS</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        @php($entry = $row->circularEntry)
        <tr>
            <td class="c">{{ $row->sl }}</td>
            <td>
                @if($entry?->cadre_name_bn_snapshot)
                    <div class="bn-title">{{ $entry->cadre_name_bn_snapshot }}</div>
                @endif
                @if($entry?->cadre_name_snapshot)
                    <div class="en">{{ $entry->cadre_name_snapshot }}</div>
                @elseif(!$entry?->cadre_name_bn_snapshot)
                    —
                @endif

                @if($entry?->sub_cadre_code)
                    @if($entry?->post_name_bn_snapshot)
                        <div class="bn" style="margin-top:.6mm;">{{ $entry->post_name_bn_snapshot }}</div>
                    @endif
                    @if($entry?->post_name_snapshot)
                        <div class="en">{{ $entry->post_name_snapshot }}</div>
                    @elseif(!$entry?->post_name_bn_snapshot)
                        <div>—</div>
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
