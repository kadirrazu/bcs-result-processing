@extends('layouts.app')
@section('title', 'Viva Result Processing')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center"><div class="col"><h2 class="page-title">Viva Result Processing · Run #{{ $run->id }}</h2><div class="text-secondary">Confidential attendance and PASS/FAIL processing · Version {{ $run->processing_version }}</div></div><div class="col-auto ms-auto"><a href="{{ route('viva.index') }}" class="btn btn-outline-secondary">Back to Viva</a></div></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card mb-3" id="viva-processing-progress" data-status-url="{{ route('viva.processing.status', $run) }}">
    <div class="card-header"><div><h3 class="card-title">Processing Progress</h3><div class="text-secondary small" id="process-step">{{ $run->current_step ?: 'Waiting in queue' }}</div></div><span class="ms-auto badge {{ \App\Support\VivaStatusPresenter::badgeClass($run->status) }}" id="process-status">{{ \App\Support\VivaStatusPresenter::label($run->status) }}</span></div>
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span id="process-count">{{ number_format($run->processed_rows) }} of {{ number_format($run->total_rows) }} records</span><span id="process-percent">{{ number_format((float)$run->progress_percent,1) }}%</span></div>
        <div class="progress"><div id="process-bar" class="progress-bar progress-bar-striped {{ in_array($run->status,['queued','running'],true) ? 'progress-bar-animated' : '' }}" style="width: {{ (float)$run->progress_percent }}%"></div></div>
        @if($run->failure_message)<div class="alert alert-danger mt-3 mb-0">{{ $run->failure_message }}</div>@endif
    </div>
</div>
<div class="row row-cards mb-3">
@foreach([
    'Academic Processed'=>$run->academic_processed_count,
    'PASS'=>$run->pass_count,
    'FAIL'=>$run->fail_count,
    'ABSENT'=>$run->absent_count,
    'CANCELLED'=>$run->cancelled_count,
    'WITHHELD'=>$run->withheld_count,
    'EXPELLED'=>$run->expelled_count,
] as $label=>$value)
<div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format((int)$value) }}</div></div></div></div>
@endforeach
</div>
@if($run->status === 'completed')
<div class="alert alert-success d-flex justify-content-between align-items-center"><div><strong>Viva result processing completed.</strong> PASS threshold: {{ $run->pass_mark }} of {{ $run->full_mark }} ({{ $run->pass_percent }}%).</div><a href="{{ route('viva.results.index') }}" class="btn btn-success">View Viva Results</a></div>
@endif
</div></div>
@if(in_array($run->status,['queued','running'],true))
<script>
document.addEventListener('DOMContentLoaded',()=>{const box=document.getElementById('viva-processing-progress');const poll=async()=>{try{const r=await fetch(box.dataset.statusUrl,{headers:{'Accept':'application/json'}});const d=await r.json();document.getElementById('process-step').textContent=d.current_step||'';document.getElementById('process-count').textContent=Number(d.processed_rows).toLocaleString()+' of '+Number(d.total_rows).toLocaleString()+' records';document.getElementById('process-percent').textContent=Number(d.progress_percent).toFixed(1)+'%';document.getElementById('process-bar').style.width=d.progress_percent+'%';if(d.finished){window.location.reload();return;}setTimeout(poll,2000);}catch(e){setTimeout(poll,4000);}};setTimeout(poll,1200);});
</script>
@endif
@endsection
