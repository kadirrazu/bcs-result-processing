<style>
body{font-family:{{ $theme->string('fonts.english_family') }},sans-serif;font-size:8.5pt;color:{{ $theme->string('colors.text') }}}
table{width:100%;border-collapse:collapse}
th,td{border:.2mm solid #cfd6df;padding:2mm 1.5mm;vertical-align:middle}
thead th{background:#f2f4f7;text-align:center;font-weight:bold}
td.num,td.serial{text-align:center}
td.serial{width:11mm}
tfoot td{font-weight:bold;background:#f8fafc}
</style>
@php($showSerial = count($report['data']['rows']) > 1)
<table>
<thead><tr>@if($showSerial)<th style="width:11mm">Serial</th>@endif<th>{{ $report['ranked_top_10'] ? 'Institution' : 'Category' }}</th><th>Total</th>@foreach($genderNames as $gender)<th>{{ $gender }}</th>@endforeach</tr></thead>
<tbody>
@forelse($report['data']['rows'] as $i=>$row)
<tr>@if($showSerial)<td class="serial">{{ $i+1 }}</td>@endif<td>{{ $row['label'] }}</td><td class="num">{{ number_format($row['total']) }}</td>@foreach($genderNames as $gender)<td class="num">{{ number_format($row['genders'][$gender] ?? 0) }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($genderNames)+2+($showSerial?1:0) }}" style="text-align:center">No data available.</td></tr>@endforelse
</tbody>
@if(count($report['data']['rows']) > 1)
<tfoot><tr>@if($showSerial)<td></td>@endif<td>{{ $report['ranked_top_10'] ? 'Total — Top 10 Institutions' : 'Total' }}</td><td class="num">{{ number_format($report['data']['total']['total'] ?? 0) }}</td>@foreach($genderNames as $gender)<td class="num">{{ number_format($report['data']['total']['genders'][$gender] ?? 0) }}</td>@endforeach</tr></tfoot>
@endif
</table>
