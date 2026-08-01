@extends('layouts.app')
@section('title', 'Written Processing')
@section('content')
<div class="page-header d-print-none"><div class="container-xl"><div class="row g-2 align-items-center">
    <div class="col"><h2 class="page-title">Written Processing</h2><div class="text-secondary">Fast Import → Validation → Warning Review → Approve/Merge → Reconciliation → Paper Crash → Finalization</div></div>
    <div class="col-auto ms-auto d-flex gap-2"><a href="{{ route('written.results') }}" class="btn btn-outline-secondary">Written Results</a><a href="{{ route('written.template') }}" class="btn btn-outline-primary">Download Import Template</a></div>
</div></div></div>

<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row row-cards mb-3">
@foreach(['results'=>'Written Results','warnings'=>'Warning Review','active'=>'Active','cancelled'=>'Cancelled','withheld'=>'Withheld'] as $key=>$label)
<div class="col-sm-6 col-lg"><div class="card card-sm"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0 {{ $key === 'warnings' ? 'text-warning' : '' }}">{{ number_format($counts[$key]) }}</div></div></div></div>
@endforeach
</div>

{{-- Active progress belongs immediately below the summary cards. It disappears after completion and page refresh. --}}
@php($latestRunStatusValue = $latestProcessingRun?->status instanceof \BackedEnum ? $latestProcessingRun->status->value : $latestProcessingRun?->status)
@if($latestProcessingRun && in_array($latestRunStatusValue, ['queued','running'], true))
<div class="card mb-3 border-primary" id="written-rule-progress" data-status-url="{{ route('written.rules.status',$latestProcessingRun) }}">
    <div class="card-header"><h3 class="card-title">Written Rule Processing</h3><div class="card-actions"><span class="badge bg-blue-lt" id="wr-status">{{ strtoupper((string) $latestRunStatusValue) }}</span></div></div>
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span id="wr-step">{{ $latestProcessingRun->current_step ?: 'Waiting for queue worker' }}</span><strong id="wr-percent">{{ number_format((float)$latestProcessingRun->progress_percent,2) }}%</strong></div>
        <div class="progress progress-lg"><div id="wr-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:{{ (float)$latestProcessingRun->progress_percent }}%"></div></div>
        <div class="text-secondary small mt-2" id="wr-count">{{ number_format((int)$latestProcessingRun->processed_rows) }} / {{ number_format((int)$latestProcessingRun->total_rows) }} rows</div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const panel=document.getElementById('written-rule-progress');if(!panel)return;const poll=async()=>{try{const r=await fetch(panel.dataset.statusUrl,{headers:{'Accept':'application/json'}});if(!r.ok){setTimeout(poll,2500);return;}const d=await r.json();document.getElementById('wr-status').textContent=String(d.status).toUpperCase();document.getElementById('wr-step').textContent=d.current_step||d.status;document.getElementById('wr-percent').textContent=Number(d.progress_percent).toFixed(2)+'%';document.getElementById('wr-bar').style.width=Number(d.progress_percent)+'%';document.getElementById('wr-count').textContent=Number(d.processed_rows).toLocaleString()+' / '+Number(d.total_rows).toLocaleString()+' rows';if(d.finished){setTimeout(()=>window.location.reload(),700);return;}setTimeout(poll,1500);}catch(e){setTimeout(poll,2500);}};poll();});
</script>
@endif

@php($runStatus = \App\Support\WrittenStatusPresenter::value($latestProcessingRun?->status))

<div class="card mb-3 shadow-sm">
    <div class="card-header bg-light"><div><h3 class="card-title mb-1">Processing Status Board</h3><div class="text-secondary small">Current Written pipeline state · GMT+6 (Asia/Dhaka)</div></div></div>
    <div class="card-body py-2">
        <div class="row g-3 align-items-center">
            <div class="col-md-6"><span class="text-secondary me-2">Current Phase</span><span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass($state->status) }}">{{ \App\Support\WrittenStatusPresenter::label($state->status) }}</span></div>
            <div class="col-md-6 text-md-end"><span class="text-secondary me-2">Rule-processing run</span><span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass($runStatus) }}">{{ $runStatus ? \App\Support\WrittenStatusPresenter::label($runStatus) : 'Not started' }}</span></div>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-vcenter card-table mb-0"><tbody>
        <tr>
            <td class="fw-medium">Written Marks Import</td>
            <td><span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass($latestBatch?->status) }}">{{ \App\Support\WrittenStatusPresenter::label($latestBatch?->status ?? 'not_started') }}</span></td>
            <td>{{ $latestBatch ? number_format((int)$latestBatch->approved_rows).' approved rows' : '—' }}</td>
        </tr>
        <tr>
            <td class="fw-medium">Eligible / Appeared / Absent</td>
            <td><span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass($state->reconciliation_generated_at ? 'generated' : 'pending') }}">{{ $state->reconciliation_generated_at ? 'Generated' : 'Pending' }}</span></td>
            <td>@if($state->reconciliation_generated_at)<div class="d-flex gap-2"><a href="{{ route('written.reconciliation') }}" class="btn btn-sm btn-outline-primary">View Report</a><form method="post" action="{{ route('written.reconciliation.generate') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Regenerate</button></form></div>@elseif(\App\Support\WrittenStatusPresenter::value($latestBatch?->status) === 'approved')<form method="post" action="{{ route('written.reconciliation.generate') }}">@csrf<button class="btn btn-sm btn-primary">Generate</button></form>@else<span class="text-secondary">Approve Written marks first</span>@endif</td>
        </tr>
        <tr>
            <td class="fw-medium">Paper Crash &amp; Track Processing</td>
            <td>@if(in_array($runStatus, ['queued','running'], true))<span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass($runStatus) }}">{{ \App\Support\WrittenStatusPresenter::label($runStatus) }}</span>@elseif($runStatus === 'failed')<span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass('failed') }}">Failed</span>@elseif($state->paper_crash_processed_at)<span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass('completed') }}">Completed</span>@else<span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass('pending') }}">Pending</span>@endif</td>
            <td class="d-flex gap-2 flex-wrap">@if($state->reconciliation_generated_at && !$state->paper_crash_processed_at && !in_array($runStatus,['queued','running'],true))<form method="post" action="{{ route('written.rules.process') }}">@csrf<button class="btn btn-sm btn-primary">Process Written Rules</button></form>@endif @if($state->paper_crash_processed_at)<a href="{{ route('written.paper-crashes') }}" class="btn btn-sm btn-outline-warning">Paper Crash Report</a><a href="{{ route('written.results') }}" class="btn btn-sm btn-outline-primary">Processed Results</a>@if(!in_array($runStatus,['queued','running'],true))<form method="post" action="{{ route('written.rules.process') }}">@csrf<button class="btn btn-sm btn-outline-secondary">Reprocess Rules</button></form>@endif @endif</td>
        </tr>
        <tr>
            <td class="fw-medium">Written Result Finalization</td>
            <td><span class="badge {{ \App\Support\WrittenStatusPresenter::badgeClass($state->result_finalized_at ? 'completed' : 'pending') }}">{{ $state->result_finalized_at ? 'Completed' : 'Pending' }}</span></td>
            <td>{{ $state->paper_crash_processed_at ? 'Ready for finalization in next phase' : 'Waiting for rule processing' }}</td>
        </tr>
    </tbody></table></div>
</div>

<div class="card mb-3"><div class="card-header"><h3 class="card-title">Import Written Marks</h3><div class="card-actions text-secondary small">XLSX / CSV · up to 100 MB</div></div><div class="card-body">
<form method="post" action="{{ route('written.import.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">@csrf
<div class="col-md"><label class="form-label">Written mark source file</label><input type="file" class="form-control" name="file" accept=".xlsx,.csv" required></div>
<div class="col-md-auto"><button class="btn btn-primary">Queue Fast Import</button></div>
</form>
<div class="text-secondary small mt-2">data_source_note is preserved as source context only and never changes Written status automatically.</div>
</div></div>

<div class="card mb-3"><div class="card-header"><h3 class="card-title">Written Rule Configuration</h3><div class="card-actions text-secondary small">Source: config/written.php</div></div><div class="table-responsive"><table class="table table-vcenter card-table"><tbody>
<tr><td>General track full mark</td><td>{{ number_format($ruleSummary['general_full_mark'],2) }}</td></tr>
<tr><td>Technical track full mark</td><td>{{ number_format($ruleSummary['technical_full_mark'],2) }}</td></tr>
<tr><td>Written pass threshold</td><td>{{ number_format((float)config('written.written_pass_percent'),2) }}%</td></tr>
<tr><td>Paper crash threshold</td><td>{{ number_format($ruleSummary['paper_crash_percent'],2) }}%</td></tr>
<tr><td>High-mark review</td><td>{{ number_format($ruleSummary['high_mark_review_percent'],2) }}%</td></tr>
<tr><td>008 + 009</td><td>Combined evaluation for crash and high-mark review</td></tr>
</tbody></table></div></div>

<div class="card mb-3"><div class="card-header"><h3 class="card-title">Written Import History</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Batch</th><th>File</th><th>Status</th><th>Rows</th><th>Warnings</th><th>Invalid</th><th>Approved</th><th></th></tr></thead><tbody>
@forelse($batches as $batch)<tr><td>#{{ $batch->id }}</td><td>{{ $batch->original_name }}</td><td>{{ str_replace('_',' ',$batch->status) }}</td><td>{{ number_format($batch->total_rows) }}</td><td class="text-warning">{{ number_format($batch->warning_rows) }}</td><td class="text-danger">{{ number_format($batch->invalid_rows) }}</td><td>{{ number_format($batch->approved_rows) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('written.import.result',$batch) }}">Open</a></td></tr>
@empty<tr><td colspan="8" class="text-center text-secondary py-4">No Written import batch yet.</td></tr>@endforelse
</tbody></table></div>@if($batches->hasPages())<div class="card-footer">{{ $batches->links() }}</div>@endif</div>

@if($audits->isNotEmpty())<div class="card"><div class="card-header"><h3 class="card-title">Latest Written Audit Events</h3></div><div class="table-responsive"><table class="table table-sm card-table"><thead><tr><th>Action</th><th>Actor</th><th>Reason</th><th>Time</th></tr></thead><tbody>@foreach($audits as $audit)<tr><td>{{ $audit->action }}</td><td>{{ $audit->actor_name ?: $audit->actor_id }}</td><td>{{ $audit->reason ?: '—' }}</td><td>{{ $audit->created_at?->format('d-m-Y h:i A') ?? '—' }}</td></tr>@endforeach</tbody></table></div></div>@endif
</div></div>
@endsection
