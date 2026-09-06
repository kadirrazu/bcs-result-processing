<!doctype html>
<html><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; color:#182433; font-size:8pt; }
table { border-collapse:collapse; width:100%; table-layout:fixed; }
th, td { border:0.2mm solid #7f8da0; padding:1.15mm 1mm; vertical-align:middle; }
thead th { background:#e9eef5; font-weight:bold; text-align:center; }
td.num { text-align:right; white-space:nowrap; } td.center { text-align:center; } td.name { text-align:left; }
tr.group-start td { border-top:0.5mm solid #344054; } tfoot td { font-weight:bold; background:#f3f5f8; }
</style></head><body>
<table>
<thead><tr>
<th style="width:22mm">Category</th><th style="width:10mm">SL</th><th style="width:20mm">Code / Abbr</th>
<th style="width:46mm">Cadre Name</th><th style="width:52mm">Post Name</th>
<th>Total Post</th><th>Total Allocated</th><th>Withheld</th><th>Cancelled</th><th>Published Active</th><th>Total Vacant</th>
</tr></thead>
<tbody>
@php($lastCategory = null)
@foreach($rows as $row)
<tr class="{{ $lastCategory !== null && $lastCategory !== $row['category'] ? 'group-start' : '' }}">
<td class="center">{{ $row['category'] }}</td><td class="center">{{ $row['serial_label'] }}</td>
<td class="center">{{ $row['cadre_code'] }} / {{ $row['cadre_abbr'] }}</td>
<td class="name">{{ $row['cadre_name'] ?: '—' }}</td><td class="name">{{ $row['post_name'] ?: '—' }}</td>
<td class="num">{{ number_format($row['total_post']) }}</td><td class="num">{{ number_format($row['total_allocated']) }}</td><td class="num">{{ number_format($row['withheld_count']) }}</td><td class="num">{{ number_format($row['cancelled_count']) }}</td><td class="num">{{ number_format($row['published_active']) }}</td><td class="num">{{ number_format($row['total_vacant']) }}</td>
</tr>
@php($lastCategory = $row['category'])
@endforeach
</tbody>
<tfoot><tr><td colspan="5" style="text-align:right">TOTAL</td><td class="num">{{ number_format($totals['total_post']) }}</td><td class="num">{{ number_format($totals['total_allocated']) }}</td><td class="num">{{ number_format($totals['withheld_count']) }}</td><td class="num">{{ number_format($totals['cancelled_count']) }}</td><td class="num">{{ number_format($totals['published_active']) }}</td><td class="num">{{ number_format($totals['total_vacant']) }}</td></tr></tfoot>
</table></body></html>
