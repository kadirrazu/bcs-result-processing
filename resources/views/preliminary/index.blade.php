@extends('layouts.app')

@section('title', 'Preliminary Processing')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl"><div class="row g-2 align-items-center">
        <div class="col"><h2 class="page-title">Preliminary Processing</h2><div class="text-secondary">Import → Validation → Approval → Reconciliation → Cut-off → Finalization</div></div>
        <div class="col-auto ms-auto d-flex gap-2"><a href="{{ route('preliminary.results.index') }}" class="btn btn-outline-primary">Preliminary Results</a><a href="{{ route('preliminary.template') }}" class="btn btn-outline-secondary">Download Import Template</a></div>
    </div></div>
</div>

<div class="page-body"><div class="container-xl">
    
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>
@endif
    
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>
@endif
    
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

    <div class="row row-cards mb-3">
        
@foreach([
            'Results'=>$counts['results'], 'Active with mark'=>$counts['active'], 'Cancelled'=>$counts['cancelled'],
            'Withheld'=>$counts['withheld'], 'Expelled'=>$counts['expelled'], 'Passed'=>$counts['passed'], 'Failed'=>$counts['failed'],
        ] as $label=>$value)
            <div class="col-sm-6 col-lg"><div class="card card-sm"><div class="card-body"><div class="text-secondary">{{ $label }}</div><div class="h2 mb-0">{{ number_format($value) }}</div></div></div></div>
        
@endforeach
    </div>

    
@if($latestFinalization && in_array($latestFinalization->status, ['queued','running'], true))
        @php
            $initialFinalizationProgress = (float) ($latestFinalization->progress_percent ?? 0);
        @endphp
        <div id="preliminary-finalization-progress" class="card mb-3 border-primary" data-status-url="{{ route('preliminary.finalization.status', $latestFinalization) }}">
            <div class="card-header"><div><h3 class="card-title mb-1">Final Preliminary Result Processing</h3><div class="text-secondary" id="finalization-current-step">{{ $latestFinalization->current_step ?: 'Waiting for queue worker' }}</div></div><div class="card-actions"><span id="finalization-live-status" class="badge bg-blue-lt">{{ \App\Support\PreliminaryStatusPresenter::label($latestFinalization->status) }}</span></div></div>
            <div class="card-body">
                <div class="progress progress-lg mb-2"><div id="finalization-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $initialFinalizationProgress }}%" aria-valuenow="{{ $initialFinalizationProgress }}" aria-valuemin="0" aria-valuemax="100"></div></div>
                <div class="d-flex justify-content-between small text-secondary"><span id="finalization-row-progress">{{ number_format((int)($latestFinalization->processed_rows ?? 0)) }} / {{ number_format((int)($latestFinalization->total_rows ?? 0)) }} rows</span><strong id="finalization-progress-percent">{{ number_format($initialFinalizationProgress,2) }}%</strong></div>
                <div id="finalization-live-error" class="alert alert-danger mt-3 mb-0 d-none"></div>
            </div>
        </div>
    
@endif

    @php
        $prelimRunStatus = \App\Support\PreliminaryStatusPresenter::value($latestFinalization?->status);
        $stateValue = \App\Support\PreliminaryStatusPresenter::value($state->status);
    @endphp
    <div class="card mb-3 shadow-sm">
        <div class="card-header bg-light"><div><h3 class="card-title mb-1">Processing Status Board</h3><div class="text-secondary small">A quick view of where the Preliminary result currently stands · GMT+6 (Asia/Dhaka)</div></div></div>
        <div class="card-body py-2"><div class="row g-3 align-items-center"><div class="col-md-6"><span class="text-secondary me-2">Current Phase</span><span class="badge {{ \App\Support\PreliminaryStatusPresenter::badgeClass($state->status) }}">{{ \App\Support\PreliminaryStatusPresenter::label($state->status) }}</span></div><div class="col-md-6 text-md-end"><span class="text-secondary me-2">Latest background task</span><span class="badge {{ \App\Support\PreliminaryStatusPresenter::badgeClass($prelimRunStatus) }}">{{ $prelimRunStatus ? \App\Support\PreliminaryStatusPresenter::label($prelimRunStatus) : 'Not started' }}</span></div></div></div>
        <div class="table-responsive"><table class="table table-vcenter card-table mb-0"><tbody>
            <tr><td class="fw-medium">Preliminary Import</td><td><span class="badge {{ \App\Support\PreliminaryStatusPresenter::badgeClass($latestBatch?->status) }}">{{ \App\Support\PreliminaryStatusPresenter::label($latestBatch?->status ?? 'not_started') }}</span></td><td>{{ $latestBatch ? number_format((int)$latestBatch->approved_rows).' approved rows' : '—' }}</td></tr>
            <tr><td class="fw-medium">Present / Absent Reconciliation</td><td><span class="badge {{ \App\Support\PreliminaryStatusPresenter::badgeClass($state->reconciliation_generated_at ? 'generated' : ($stateValue === 'reopened' ? 'reopened' : 'pending')) }}">{{ $state->reconciliation_generated_at ? 'Ready' : ($stateValue === 'reopened' ? 'Needs regeneration' : 'Pending') }}</span></td><td><div class="d-flex gap-2 flex-wrap">
@can('process', App\Models\PreliminaryResult::class)
@if($latestBatch && \App\Support\PreliminaryStatusPresenter::value($latestBatch->status)==='approved')<form method="post" action="{{ route('preliminary.reconciliation.generate') }}">@csrf<button class="btn btn-sm {{ $state->reconciliation_generated_at ? 'btn-outline-secondary' : 'btn-primary' }}">{{ $state->reconciliation_generated_at ? 'Regenerate' : 'Generate' }}</button></form>
@endif
@endcan
 
@if($latestReconciliation)<a class="btn btn-sm btn-outline-primary" href="{{ route('preliminary.reconciliation.show',$latestReconciliation) }}">View Report</a>
@endif
</div></td></tr>
            <tr><td class="fw-medium">Mark Distribution</td><td><span class="badge {{ \App\Support\PreliminaryStatusPresenter::badgeClass($state->distribution_generated_at ? 'generated' : 'pending') }}">{{ $state->distribution_generated_at ? 'Ready' : 'Pending' }}</span></td><td><div class="d-flex gap-2 flex-wrap">
@can('process', App\Models\PreliminaryResult::class)
@if($state->latest_reconciliation_report_id)<form method="post" action="{{ route('preliminary.distribution.generate') }}">@csrf<button class="btn btn-sm {{ $state->distribution_generated_at ? 'btn-outline-secondary' : 'btn-primary' }}">{{ $state->distribution_generated_at ? 'Regenerate' : 'Generate' }}</button></form>
@endif
@endcan
 
@if($latestDistribution)<a class="btn btn-sm btn-outline-primary" href="{{ route('preliminary.distribution.show',$latestDistribution) }}">View Distribution</a>
@endif
</div></td></tr>
            <tr><td class="fw-medium">Cut-off Mark</td><td>
@if($state->cutoff_mark !== null && !$state->cutoff_requires_review)<span class="badge bg-green-lt text-green">Approved · {{ number_format((float)$state->cutoff_mark,2) }}</span>
@elseif($state->cutoff_requires_review)<span class="badge bg-yellow-lt text-yellow">Review required</span>
@elseif($pendingCutoff)<span class="badge bg-azure-lt text-azure">Awaiting approval · {{ number_format((float)$pendingCutoff->cutoff_mark,2) }}</span>
@else<span class="badge bg-secondary-lt text-secondary">Pending</span>
@endif
</td><td>
@if($latestDistribution)<a class="btn btn-sm btn-outline-warning" href="{{ route('preliminary.distribution.show',$latestDistribution) }}">Manage Cut-off</a>
@else<span class="text-secondary">Generate mark distribution first.</span>
@endif
</td></tr>
            <tr><td class="fw-medium">Final Preliminary Result</td><td id="finalization-board-status">
@if($latestFinalization && in_array($latestFinalization->status,['queued','running'],true))<span class="badge {{ \App\Support\PreliminaryStatusPresenter::badgeClass($latestFinalization->status) }}">{{ \App\Support\PreliminaryStatusPresenter::label($latestFinalization->status) }}</span>
@elseif($state->result_finalized_at)<span class="badge bg-green-lt text-green">Finalized</span>
@elseif($latestFinalization && $latestFinalization->status==='failed')<span class="badge bg-red-lt text-red">Needs attention</span>
@elseif($state->cutoff_mark !== null && !$state->cutoff_requires_review)<span class="badge bg-teal-lt text-teal">Ready for finalization</span>
@else<span class="badge bg-secondary-lt text-secondary">Pending</span>
@endif
</td><td id="finalization-board-completed-at"><div class="d-flex gap-2 flex-wrap">
@if($state->result_finalized_at)<a class="btn btn-sm btn-outline-success" href="{{ route('preliminary.final-result.combined') }}">Combined Result</a><a class="btn btn-sm btn-outline-success" href="{{ route('preliminary.final-result.category') }}">Category-wise Result</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('preliminary.final-result.template') }}">Fill Result Template</a>
@else<span class="text-secondary">Complete the current Preliminary steps first.</span>
@endif
</div></td></tr>
        </tbody></table></div>
    </div>

    
@can('process', App\Models\PreliminaryResult::class)
        
@if($state->cutoff_mark !== null && !$state->cutoff_requires_review && !($latestFinalization && in_array($latestFinalization->status,['queued','running'],true)))
            <div class="card mb-3"><div class="card-header"><h3 class="card-title">Final Preliminary Result Processing</h3></div><div class="card-body"><p class="text-secondary">Apply the approved cut-off <strong>{{ number_format((float)$state->cutoff_mark,2) }}</strong> to the current eligible candidates. The action is recorded in the audit history and Preliminary log.</p><form method="post" action="{{ route('preliminary.finalization.store') }}">@csrf<div class="row g-2 align-items-end"><div class="col-md"><label class="form-label">Reason / authority reference</label><input class="form-control" name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="2000" placeholder="For example: Reviewed against the approved cut-off and cleared for final result"></div><div class="col-md-auto"><button class="btn btn-success" onclick="return confirm('Finalize Preliminary result using the approved cut-off?');">{{ $state->result_finalized_at ? 'Re-Finalize Result' : 'Finalize Result' }}</button></div></div></form></div></div>
        
@endif
    
@endcan

    
@if(is_array($state->summary) && data_get($state->summary,'finalization'))
        <div class="card mb-3"><div class="card-header"><h3 class="card-title">Final Result Summary</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Result</th><th class="text-end fw-bold">Total</th><th class="text-end">GG</th><th class="text-end">TT</th><th class="text-end">GT</th></tr></thead><tbody>
@foreach(['pass'=>'Passed','fail'=>'Failed','cancelled'=>'Cancelled','withheld'=>'Withheld','expelled'=>'Expelled','absent'=>'Absent'] as $key=>$label)<tr><td>{{ $label }}</td><td class="text-end fw-bold">{{ number_format((int)data_get($state->summary,'finalization.'.$key.'.total',0)) }}</td><td class="text-end">{{ number_format((int)data_get($state->summary,'finalization.'.$key.'.GG',0)) }}</td><td class="text-end">{{ number_format((int)data_get($state->summary,'finalization.'.$key.'.TT',0)) }}</td><td class="text-end">{{ number_format((int)data_get($state->summary,'finalization.'.$key.'.GT',0)) }}</td></tr>
@endforeach
</tbody></table></div></div>
    
@endif

    <div class="card mb-3">
        <div class="card-header"><div><h3 class="card-title mb-1">Reports &amp; Exports</h3><div class="text-secondary small">Common result, publishing and administrative outputs in one place. The same actions remain available on their detailed pages.</div></div></div>
        <div class="card-body">
            
@if($state->result_finalized_at && !$state->cutoff_requires_review)
                <div class="d-flex gap-2 flex-wrap mb-3"><a class="btn btn-outline-success" href="{{ route('preliminary.final-result.combined') }}">Combined Result</a><a class="btn btn-outline-success" href="{{ route('preliminary.final-result.category') }}">Category-wise Result</a><a class="btn btn-outline-secondary" href="{{ route('preliminary.final-result.template') }}">Fill Result Template</a></div>
                <div class="row g-3">
                    
@foreach(['passed'=>'Passed candidates','all'=>'All participants'] as $scope=>$label)
                        <div class="col-lg-6"><div class="border rounded p-3 h-100"><div class="fw-semibold mb-2">{{ $label }} · Excel</div><form method="get" action="{{ route('preliminary.exports.xlsx') }}" class="row g-2 align-items-end"><input type="hidden" name="scope" value="{{ $scope }}"><div class="col-sm-5"><label class="form-label">Order by</label><select class="form-select" name="order"><option value="reg">Registration number</option><option value="mark">Preliminary mark</option></select></div><div class="col-sm-4"><label class="form-label">Direction</label><select class="form-select" name="direction"><option value="asc">Ascending</option><option value="desc">Descending</option></select></div><div class="col-sm-3"><button class="btn btn-primary w-100">Export XLSX</button></div></form></div></div>
                    
@endforeach
                </div>
            
@else
                <div class="text-secondary">Administrative result exports will be available after the Preliminary result is finalized and current.</div>
            
@endif
        </div>
    </div>

    
@can('process', App\Models\PreliminaryResult::class)
        <div class="card mb-3"><div class="card-header"><h3 class="card-title">Preliminary Import</h3></div><div class="card-body"><p class="text-secondary">Use exactly four columns: <code>user</code>, <code>reg</code>, <code>mark</code>, <code>candidate_status</code>.</p><form method="post" enctype="multipart/form-data" action="{{ route('preliminary.import.store') }}">@csrf<div class="row g-2 align-items-end"><div class="col-md"><label class="form-label">XLSX / CSV file</label><input class="form-control" type="file" name="file" accept=".xlsx,.csv" required></div><div class="col-md-auto"><button class="btn btn-primary">Queue Fast Import</button></div></div></form></div></div>
    
@endcan

    <div class="card mb-3"><div class="card-header"><h3 class="card-title">Import History</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Batch</th><th>File</th><th>Status</th><th>Rows</th><th>Valid</th><th>Warnings</th><th>Invalid</th><th>Approved</th><th></th></tr></thead><tbody>
        
@forelse($batches as $batch)<tr><td>#{{ $batch->id }}</td><td>{{ $batch->original_name }}</td><td><span class="badge {{ \App\Support\PreliminaryStatusPresenter::badgeClass($batch->status) }}">{{ \App\Support\PreliminaryStatusPresenter::label($batch->status) }}</span></td><td>{{ number_format($batch->total_rows) }}</td><td>{{ number_format($batch->valid_rows) }}</td><td class="text-warning">{{ number_format($batch->warning_rows) }}</td><td class="text-danger">{{ number_format($batch->invalid_rows) }}</td><td>{{ number_format($batch->approved_rows) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('preliminary.import.result',$batch) }}">Open</a></td></tr>@empty<tr><td colspan="9" class="text-center text-secondary py-4">No Preliminary import batches yet.</td></tr>
@endforelse
    </tbody></table></div><div class="card-footer d-flex align-items-center justify-content-between gap-3 flex-wrap"><x-pagination-summary :paginator="$batches" />
@if($batches->hasPages()){{ $batches->links() }}
@endif
</div></div>

    <div class="card"><div class="card-header"><h3 class="card-title">Recent Preliminary Audit</h3></div><div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Action</th><th>Actor</th><th>Reason</th><th>Time</th></tr></thead><tbody>
@forelse($audits as $audit)<tr><td>{{ $audit->action }}</td><td>{{ $audit->actor_name ?? $audit->actor_id }}</td><td>{{ $audit->reason ?? '—' }}</td><td>{{ $audit->created_at?->format('d-m-Y h:i A') }}</td></tr>@empty<tr><td colspan="4" class="text-center text-secondary">No audit entries yet.</td></tr>
@endforelse
</tbody></table></div></div>
</div></div>
@endsection

@push('scripts')
@if($latestFinalization && in_array($latestFinalization->status, ['queued','running'], true))
<script>
document.addEventListener('DOMContentLoaded',()=>{const card=document.getElementById('preliminary-finalization-progress');if(!card)return;const bar=document.getElementById('finalization-progress-bar');const percent=document.getElementById('finalization-progress-percent');const rows=document.getElementById('finalization-row-progress');const step=document.getElementById('finalization-current-step');const status=document.getElementById('finalization-live-status');const errorBox=document.getElementById('finalization-live-error');const fmt=new Intl.NumberFormat('en-US');let stopped=false;const poll=async()=>{if(stopped)return;try{const response=await fetch(card.dataset.statusUrl,{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)throw new Error('Status request failed');const data=await response.json();const progress=Math.max(0,Math.min(100,Number(data.progress_percent||0)));bar.style.width=progress+'%';bar.setAttribute('aria-valuenow',progress.toFixed(2));percent.textContent=progress.toFixed(2)+'%';rows.textContent=fmt.format(Number(data.processed_rows||0))+' / '+fmt.format(Number(data.total_rows||0))+' rows';step.textContent=data.current_step||data.status;status.textContent=data.status==='completed'?'Completed':data.status==='failed'?'Needs attention':'In progress';status.className='badge '+(data.status==='failed'?'bg-red-lt':data.status==='completed'?'bg-green-lt':'bg-blue-lt');if(data.status==='failed'){stopped=true;bar.classList.remove('progress-bar-animated');bar.classList.add('bg-danger');errorBox.textContent=data.failure_message||'Preliminary finalization needs attention.';errorBox.classList.remove('d-none');setTimeout(()=>window.location.reload(),1500);return;}if(data.status==='completed'){stopped=true;bar.style.width='100%';bar.classList.remove('progress-bar-animated');setTimeout(()=>window.location.reload(),800);return;}}catch(e){console.warn('Preliminary progress polling failed:',e);}setTimeout(poll,1500);};poll();});
</script>
@endif
@endpush
