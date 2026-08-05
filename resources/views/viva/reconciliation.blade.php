@extends('layouts.app')
@section('title', 'Viva Reconciliation')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title">Viva Reconciliation · Run #{{ $run->id }}</h2><div class="text-secondary">Eligibility, attendance and review reconciliation before Viva PASS/FAIL processing.</div></div><div class="col-auto ms-auto"><a href="{{ route('viva.index') }}" class="btn btn-outline-secondary">Back to Viva</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(in_array($run->status, ['queued','running'], true))
<div class="card mb-3" id="recon-progress" data-status-url="{{ route('viva.reconciliation.status', $run) }}"><div class="card-body"><div class="d-flex justify-content-between mb-2"><strong>Reconciliation in progress</strong><span id="pct">{{ number_format((float)$run->progress_percent,1) }}%</span></div><div class="progress"><div id="bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:{{ (float)$run->progress_percent }}%"></div></div><div id="count" class="text-secondary small mt-2">{{ number_format($run->processed_candidates) }} of {{ number_format($run->total_candidates) }} reviewed</div></div></div>
@elseif($run->status === 'failed')
<div class="alert alert-danger"><strong>Reconciliation failed.</strong><div class="mt-1">{{ $run->failure_message }}</div></div>
@else
<div class="row row-cards mb-3">
@foreach([
'Written-qualified / Viva eligible'=>$run->eligible_count,
'Mapped'=>$run->mapped_count,
'Board data'=>$run->board_data_count,
'Missing mapping'=>$run->missing_mapping_count,
'Missing Board data'=>$run->missing_board_count,
'Appeared'=>$run->appeared_count,
'Absent'=>$run->absent_count,
'Warnings'=>$run->warning_count,
] as $label=>$value)
<div class="col-sm-6 col-lg-3"><div class="card card-sm h-100"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format($value) }}</div></div></div></div>
@endforeach
</div>
<div class="row row-cards mb-3">
<div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Commission Review Flags</h3></div><div class="table-responsive"><table class="table card-table table-vcenter"><tbody><tr><td>Quota mismatch</td><td class="text-end fw-semibold">{{ number_format($run->quota_mismatch_count) }}</td></tr><tr><td>CFF mismatch</td><td class="text-end">{{ number_format($run->quota_cff_mismatch_count) }}</td></tr><tr><td>EM mismatch</td><td class="text-end">{{ number_format($run->quota_em_mismatch_count) }}</td></tr><tr><td>PHC mismatch</td><td class="text-end">{{ number_format($run->quota_phc_mismatch_count) }}</td></tr><tr><td>Source invalid flag</td><td class="text-end">{{ number_format($run->source_invalid_count) }}</td></tr><tr><td>Source issue flag</td><td class="text-end">{{ number_format($run->source_issue_count) }}</td></tr><tr><td>High-mark review</td><td class="text-end">{{ number_format($run->high_mark_count) }}</td></tr></tbody></table></div><div class="card-footer"><a href="{{ route('viva.reviews') }}" class="btn btn-warning">Open Review Listing</a></div></div></div>
<div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Operational Status</h3></div><div class="table-responsive"><table class="table card-table table-vcenter"><tbody><tr><td>ACTIVE</td><td class="text-end">{{ number_format($run->active_count) }}</td></tr><tr><td>CANCELLED</td><td class="text-end">{{ number_format($run->cancelled_count) }}</td></tr><tr><td>WITHHELD</td><td class="text-end">{{ number_format($run->withheld_count) }}</td></tr><tr><td>EXPELLED</td><td class="text-end">{{ number_format($run->expelled_count) }}</td></tr></tbody></table></div></div></div>
</div>
<div class="row row-cards">
<div class="col-lg-6"><div class="card"><div class="card-header"><h3 class="card-title">Written Qualified Track</h3></div><div class="table-responsive"><table class="table card-table"><tbody>@foreach((array)$run->track_summary as $track=>$count)<tr><td>{{ $track }}</td><td class="text-end">{{ number_format($count) }}</td></tr>@endforeach</tbody></table></div></div></div>
<div class="col-lg-6"><div class="card"><div class="card-header"><h3 class="card-title">Original Category</h3></div><div class="table-responsive"><table class="table card-table"><tbody>@foreach((array)$run->category_summary as $category=>$count)<tr><td>{{ [1=>'1 - GG',2=>'2 - TT',3=>'3 - GT'][(int)$category] ?? $category }}</td><td class="text-end">{{ number_format($count) }}</td></tr>@endforeach</tbody></table></div></div></div>
</div>
@endif
</div></div>
@if(in_array($run->status, ['queued','running'], true))
<script>(()=>{const p=document.getElementById('recon-progress');const poll=async()=>{try{const r=await fetch(p.dataset.statusUrl,{headers:{Accept:'application/json'}});if(r.ok){const d=await r.json();const n=Number(d.progress_percent||0);document.getElementById('bar').style.width=n+'%';document.getElementById('pct').textContent=n.toFixed(1)+'%';document.getElementById('count').textContent=Number(d.processed_candidates).toLocaleString()+' of '+Number(d.total_candidates).toLocaleString()+' reviewed';if(d.finished){location.reload();return;}}}catch(e){}setTimeout(poll,2500)};setTimeout(poll,1500)})();</script>
@endif
@endsection
