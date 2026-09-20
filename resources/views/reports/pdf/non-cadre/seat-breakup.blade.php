<!doctype html>
<html><head><meta charset="utf-8"><style>
body{font-family:'{{ $fontFamily }}';font-size:9pt;color:#111827} .summary{width:100%;border-collapse:collapse;margin:2mm 0 4mm}.summary td{border:1px solid #d0d5dd;padding:2mm;text-align:center}.grade-title{font-weight:bold;font-size:10pt;margin:3mm 0 1.5mm}.data{width:100%;border-collapse:collapse;margin-bottom:3mm}.data th,.data td{border:1px solid #98a2b3;padding:1.5mm}.data th{font-weight:bold;text-align:center;background:#f2f4f7}.c{text-align:center;vertical-align:middle}.r{text-align:right;vertical-align:middle}.post{vertical-align:middle}.total td{font-weight:bold;background:#f9fafb}
</style></head><body>
<table class="summary"><tr><td><strong>Status</strong><br>{{ strtoupper($version->is_stale ? 'STALE' : $version->status) }}</td><td><strong>Total Post</strong><br>{{ number_format($totals['total']) }}</td><td><strong>Merit/MQ</strong><br>{{ number_format($totals['mq']) }}</td><td><strong>CFF</strong><br>{{ number_format($totals['cff']) }}</td><td><strong>EM</strong><br>{{ number_format($totals['em']) }}</td><td><strong>PHC</strong><br>{{ number_format($totals['phc']) }}</td></tr></table>
@foreach($groups as $grade => $group)
<div class="grade-title">Grade: {{ $grade }}</div>
<table class="data"><thead><tr><th>Serial</th><th>Post Name</th><th>Post Code</th><th>Total</th><th>Merit/MQ</th><th>CFF</th><th>EM</th><th>PHC</th></tr></thead><tbody>
@foreach($group as $row)<tr><td class="c">{{ $row->post_serial }}{{ $row->post_sub_serial !== null ? '.'.$row->post_sub_serial : '' }}</td><td class="post"><strong>{{ $row->post_title }}</strong><br><span style="font-size:8pt;color:#667085">{{ $row->entity }}</span></td><td class="c">{{ $row->post_code }}</td><td class="c">{{ $row->total_post }}</td><td class="c">{{ $row->mq_post }}</td><td class="c">{{ $row->cff_post }}</td><td class="c">{{ $row->em_post }}</td><td class="c">{{ $row->phc_post }}</td></tr>@endforeach
</tbody><tfoot><tr class="total"><td colspan="3" class="r">TOTAL — GRADE {{ $grade }}</td><td class="c">{{ $group->sum('total_post') }}</td><td class="c">{{ $group->sum('mq_post') }}</td><td class="c">{{ $group->sum('cff_post') }}</td><td class="c">{{ $group->sum('em_post') }}</td><td class="c">{{ $group->sum('phc_post') }}</td></tr></tfoot></table>
@endforeach
</body></html>
