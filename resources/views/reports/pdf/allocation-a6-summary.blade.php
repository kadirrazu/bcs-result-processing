<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; color:#182433; font-size:5.7pt; }
table { border-collapse:collapse; width:100%; table-layout:fixed; }
th, td { border:0.18mm solid #7f8da0; padding:0.8mm 0.7mm; vertical-align:middle; }
thead th { background:#e9eef5; font-weight:bold; text-align:center; line-height:1.15; }
tbody td.num, tfoot td.num { text-align:right; white-space:nowrap; }
tbody td.center { text-align:center; }
tbody td.name { text-align:left; }
tbody tr.group-start td { border-top:0.45mm solid #344054; }
tfoot td { font-weight:bold; background:#f3f5f8; }
.muted { color:#667085; }
</style>
</head>
<body>
<table>
    <thead>
        <tr>
            <th rowspan="2" style="width:19mm">Category</th>
            <th rowspan="2" style="width:8mm">SL</th>
            <th rowspan="2" style="width:15mm">Code / Abbr</th>
            <th rowspan="2" style="width:29mm">Cadre Name</th>
            <th rowspan="2" style="width:33mm">Post Name</th>
            <th colspan="6">Overall</th>
            <th colspan="5">Merit Pool</th>
            <th colspan="4">CFF</th>
            <th colspan="4">EM</th>
            <th colspan="4">PHC</th>
            <th colspan="3">Phase-2 Movement</th>
        </tr>
        <tr>
            <th>Post</th><th>Allocated</th><th>Withheld</th><th>Cancelled</th><th>Published Active</th><th>Vacant</th>
            <th>Original MQ</th><th>NM Converted In</th><th>Capacity</th><th>Allocated</th><th>Rest</th>
            <th>Post</th><th>Allocated</th><th>NM Converted</th><th>Rest</th>
            <th>Post</th><th>Allocated</th><th>NM Converted</th><th>Rest</th>
            <th>Post</th><th>Allocated</th><th>NM Converted</th><th>Rest</th>
            <th>NM Alloc.</th><th>Shifted</th><th>Quota to Merit</th>
        </tr>
    </thead>
    <tbody>
    @php($lastCategory = null)
    @foreach($rows as $row)
        <tr class="{{ $lastCategory !== null && $lastCategory !== $row['category'] ? 'group-start' : '' }}">
            <td class="center">{{ $row['category'] }}</td>
            <td class="center">{{ $row['serial_label'] }}</td>
            <td class="center">{{ $row['cadre_code'] }} / {{ $row['cadre_abbr'] }}</td>
            <td class="name">{{ $row['cadre_name'] ?: '—' }}</td>
            <td class="name">{{ $row['post_name'] ?: '—' }}</td>
            <td class="num">{{ number_format($row['total_post']) }}</td>
            <td class="num">{{ number_format($row['total_allocated']) }}</td>
            <td class="num">{{ number_format($row['withheld_count']) }}</td>
            <td class="num">{{ number_format($row['cancelled_count']) }}</td>
            <td class="num">{{ number_format($row['published_active']) }}</td>
            <td class="num">{{ number_format($row['total_vacant']) }}</td>
            <td class="num">{{ number_format($row['mq_post']) }}</td>
            <td class="num">{{ number_format($row['converted_in']) }}</td>
            <td class="num">{{ number_format($row['merit_capacity']) }}</td>
            <td class="num">{{ number_format($row['merit_allocated']) }}</td>
            <td class="num">{{ number_format($row['merit_rest']) }}</td>
            <td class="num">{{ number_format($row['cff_post']) }}</td>
            <td class="num">{{ number_format($row['cff_allocated']) }}</td>
            <td class="num">{{ number_format($row['cff_converted']) }}</td>
            <td class="num">{{ number_format($row['cff_rest']) }}</td>
            <td class="num">{{ number_format($row['em_post']) }}</td>
            <td class="num">{{ number_format($row['em_allocated']) }}</td>
            <td class="num">{{ number_format($row['em_converted']) }}</td>
            <td class="num">{{ number_format($row['em_rest']) }}</td>
            <td class="num">{{ number_format($row['phc_post']) }}</td>
            <td class="num">{{ number_format($row['phc_allocated']) }}</td>
            <td class="num">{{ number_format($row['phc_converted']) }}</td>
            <td class="num">{{ number_format($row['phc_rest']) }}</td>
            <td class="num">{{ number_format($row['nm_allocations']) }}</td>
            <td class="num">{{ number_format($row['shifted_allocations']) }}</td>
            <td class="num">{{ number_format($row['quota_to_merit']) }}</td>
        </tr>
        @php($lastCategory = $row['category'])
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" style="text-align:right">TOTAL</td>
            <td class="num">{{ number_format($totals['total_post']) }}</td>
            <td class="num">{{ number_format($totals['total_allocated']) }}</td>
            <td class="num">{{ number_format($totals['withheld_count']) }}</td>
            <td class="num">{{ number_format($totals['cancelled_count']) }}</td>
            <td class="num">{{ number_format($totals['published_active']) }}</td>
            <td class="num">{{ number_format($totals['total_vacant']) }}</td>
            <td class="num">{{ number_format($totals['mq_post']) }}</td>
            <td class="num">{{ number_format($totals['converted_in']) }}</td>
            <td class="num">{{ number_format($totals['merit_capacity']) }}</td>
            <td class="num">{{ number_format($totals['merit_allocated']) }}</td>
            <td class="num">{{ number_format($totals['merit_rest']) }}</td>
            <td class="num">{{ number_format($totals['cff_post']) }}</td>
            <td class="num">{{ number_format($totals['cff_allocated']) }}</td>
            <td class="num">{{ number_format($totals['cff_converted']) }}</td>
            <td class="num">{{ number_format($totals['cff_rest']) }}</td>
            <td class="num">{{ number_format($totals['em_post']) }}</td>
            <td class="num">{{ number_format($totals['em_allocated']) }}</td>
            <td class="num">{{ number_format($totals['em_converted']) }}</td>
            <td class="num">{{ number_format($totals['em_rest']) }}</td>
            <td class="num">{{ number_format($totals['phc_post']) }}</td>
            <td class="num">{{ number_format($totals['phc_allocated']) }}</td>
            <td class="num">{{ number_format($totals['phc_converted']) }}</td>
            <td class="num">{{ number_format($totals['phc_rest']) }}</td>
            <td class="num">{{ number_format($totals['nm_allocations']) }}</td>
            <td class="num">{{ number_format($totals['shifted_allocations']) }}</td>
            <td class="num">{{ number_format($totals['quota_to_merit']) }}</td>
        </tr>
    </tfoot>
</table>
</body>
</html>
