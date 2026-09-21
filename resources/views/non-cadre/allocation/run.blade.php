@extends('layouts.app')
@section('content')
<div class="container-xl">
    <div class="page-header mb-4">
        <div class="row align-items-center g-3">
            <div class="col">
                <div class="page-pretitle">Non-Cadre Processing · NC4</div>
                <h2 class="page-title">Non-Cadre Allocation — Version {{ $run->version }}</h2>
                <div class="text-secondary mt-1">
                    Current stage: <strong>{{ $run->phase }}</strong>
                    <span class="mx-1">·</span>
                    Status: <strong id="nc4-status">{{ strtoupper($run->status) }}</strong>
                </div>
            </div>
            <div class="col-auto"><a href="{{ route('non-cadre.allocation.index') }}" class="btn btn-outline-secondary">Back to NC4</a></div>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger mb-4">{{ $errors->first() }}</div>@endif

    @if(in_array($run->status,['queued','processing','failed'],true))
        <div class="card mb-4" id="nc4-queue-card">
            <div class="card-header"><h3 class="card-title">Queue Processing</h3></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <div><div class="text-secondary">Current queued stage</div><strong id="nc4-queue-stage">{{ $run->queue_stage ?? $run->phase }}</strong></div>
                    <span class="badge {{ $run->status==='failed'?'bg-red-lt':'bg-blue-lt' }}" id="nc4-queue-badge">{{ strtoupper($run->status) }}</span>
                </div>
                <div class="progress mt-3"><div id="nc4-progress-bar" class="progress-bar" style="width: {{ (int)($run->progress_percent ?? 0) }}%">{{ (int)($run->progress_percent ?? 0) }}%</div></div>
                @if($run->failure_message)<div class="alert alert-danger mt-3 mb-0" id="nc4-failure">{{ $run->failure_message }}</div>@else<div class="alert alert-danger mt-3 mb-0 d-none" id="nc4-failure"></div>@endif
                <div class="text-secondary mt-2">Processing runs through the existing <strong>imports</strong> queue. You may leave this page and return later.</div>
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Allocation Summary</h3></div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-6 col-md"><div class="text-secondary">Candidates</div><div class="h2 mb-0">{{ number_format($run->total_candidates) }}</div></div>
                <div class="col-6 col-md"><div class="text-secondary">Allocated</div><div class="h2 mb-0">{{ number_format($summary['allocated']) }}</div></div>
                <div class="col-6 col-md"><div class="text-secondary">Quota Allocated</div><div class="h2 mb-0">{{ number_format($summary['quota']) }}</div><div class="text-secondary small mt-1">CFF {{ $summary['CFF'] }} · EM {{ $summary['EM'] }} · PHC {{ $summary['PHC'] }}</div></div>
                <div class="col-6 col-md"><div class="text-secondary">MQ / Merit</div><div class="h2 mb-0">{{ number_format($summary['MQ']) }}</div></div>
                <div class="col-6 col-md"><div class="text-secondary">NM</div><div class="h2 mb-0">{{ number_format($run->nm_count ?? 0) }}</div></div>
                <div class="col-6 col-md"><div class="text-secondary">Shifted</div><div class="h2 mb-0">{{ number_format($run->shifted_count ?? 0) }}</div></div>
                <div class="col-6 col-md"><div class="text-secondary">Quota → Merit</div><div class="h2 mb-0">{{ number_format($run->quota_to_merit_count ?? 0) }}</div></div>
            </div>
        </div>
        <div class="card-footer">
            <div class="d-flex gap-2 flex-wrap">
                @if(!in_array($run->status,['queued','processing'],true) && $run->phase==='INPUT_FREEZE' && ($run->queue_stage ?? null)!=='INPUT_FREEZE')<form method="post" action="{{ route('non-cadre.allocation.phase1',$run->id) }}">@csrf<button class="btn btn-primary">Run NC4.2 — Phase-1</button></form>@endif
                @if(!in_array($run->status,['queued','processing'],true) && $run->phase==='PHASE1')<form method="post" action="{{ route('non-cadre.allocation.phase2',$run->id) }}">@csrf<button class="btn btn-primary">Run NC4.3 — Shifting + NM</button></form>@endif
                @if(!in_array($run->status,['queued','processing'],true) && $run->phase==='PHASE2')<form method="post" action="{{ route('non-cadre.allocation.validate',$run->id) }}">@csrf<button class="btn btn-primary">Run NC4.4 — Allocation Validation</button></form>@endif
                @if($run->phase==='VALIDATION' && $run->status==='validated')<form method="post" action="{{ route('non-cadre.allocation.finalize',$run->id) }}">@csrf<button class="btn btn-success">Finalize NC4 Allocation</button></form>@endif
            </div>
        </div>
    </div>

    @if($reviews->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header"><div><h3 class="card-title">Special Requirement Review</h3><div class="text-secondary mt-1">Review pending candidates before Allocation Validation.</div></div></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Candidate Information</th><th>Post</th><th>Special Requirement</th><th class="text-center">Decision</th><th>Review Action / Reason</th></tr></thead>
                    <tbody>
                    @foreach($reviews as $rv)
                        <tr>
                            <td style="min-width:260px">
                                <div><strong>{{ $rv->name ?: '—' }}</strong></div>
                                <div class="text-secondary mt-1"><span class="fw-semibold">Reg:</span> {{ $rv->reg }}</div>
                                <div class="mt-1"><span class="fw-semibold">Sex:</span> {{ $rv->sex_label }}</div>
                                <div class="mt-1"><span class="fw-semibold">Bachelor Subject:</span> {{ $rv->bachelor_subject_label }}</div>
                            </td>
                            <td><strong>{{ $rv->post_code }}</strong><div class="text-secondary mt-1">{{ $rv->post_title }}</div></td>
                            <td style="min-width:220px">{{ $rv->special_requirement_note }}</td>
                            <td class="text-center"><span class="badge {{ $rv->decision==='APPROVED'?'bg-green-lt':($rv->decision==='REJECTED'?'bg-red-lt':'bg-yellow-lt') }}">{{ $rv->decision }}</span></td>
                            <td style="min-width:360px">
                                @if($rv->decision==='PENDING')
                                    <form method="post" action="{{ route('non-cadre.allocation.review',[$run->id,$rv->id]) }}" class="row g-2">@csrf
                                        <div class="col-md-4"><select name="decision" class="form-select form-select-sm" required><option value="APPROVED">Approve</option><option value="REJECTED">Reject</option></select></div>
                                        <div class="col-md-8"><input name="reason" class="form-control form-control-sm" placeholder="Mandatory reason" required></div>
                                        <div class="col-12"><button class="btn btn-sm btn-primary">Save Review</button></div>
                                    </form>
                                @else
                                    <div class="text-secondary">{{ $rv->reason }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($checks->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">Allocation Validation Checks</h3></div>
            <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Check</th><th class="text-center">Status</th><th>Message</th></tr></thead><tbody>@foreach($checks as $c)<tr><td><strong>{{ $c->check_code }}</strong></td><td class="text-center"><span class="badge {{ $c->status==='PASS'?'bg-green-lt':'bg-red-lt' }}">{{ $c->status }}</span></td><td>{{ $c->message }}</td></tr>@endforeach</tbody></table></div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><div><h3 class="card-title">Post-wise Allocation Summary</h3><div class="text-secondary mt-1">Against the circular and frozen NC2 seat breakup used by this allocation run.</div></div></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th class="text-center">Grade</th><th class="text-center">Serial</th><th class="text-center">Post Code</th><th>Post Name</th><th class="text-center">Total Post</th><th class="text-center">MQ Post</th><th class="text-center">CFF</th><th class="text-center">EM</th><th class="text-center">PHC</th><th class="text-center">Allocated</th><th class="text-center">MQ Alloc.</th><th class="text-center">Quota Alloc.</th><th class="text-center">Vacant</th></tr></thead><tbody>
        @foreach($postSummary as $p)<tr><td class="text-center">{{ $p->post_grade ?: '—' }}</td><td class="text-center">{{ $p->post_sub_serial ? $p->post_serial.'.'.$p->post_sub_serial : $p->post_serial }}</td><td class="text-center"><strong>{{ $p->post_code }}</strong></td><td><a href="{{ route('non-cadre.allocation.run', ['run' => $run->id, 'post_code' => $p->post_code, 'allocation' => 'allocated']) }}#current-stage-result" class="fw-semibold text-reset text-decoration-underline">{{ $p->post_title }}</a></td><td class="text-center"><strong>{{ $p->post_count }}</strong></td><td class="text-center">{{ $p->mq_post ?? 0 }}</td><td class="text-center">{{ $p->cff_post ?? 0 }}</td><td class="text-center">{{ $p->em_post ?? 0 }}</td><td class="text-center">{{ $p->phc_post ?? 0 }}</td><td class="text-center"><strong>{{ $p->allocated }}</strong></td><td class="text-center">{{ $p->mq_allocated }}</td><td class="text-center">{{ $p->cff_allocated+$p->em_allocated+$p->phc_allocated }}</td><td class="text-center"><strong>{{ $p->vacant }}</strong></td></tr>@endforeach
        </tbody></table></div>
    </div>

    <div class="card mb-4" id="current-stage-result">
        <div class="card-header"><div><h3 class="card-title">Current Stage Result</h3><div class="text-secondary mt-1">Candidate-wise allocation output for the current NC4 stage.</div></div></div>
        <div class="card-body border-bottom"><form method="get" class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label">Candidate Search</label><input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Registration, User ID or Name"></div><div class="col-md-2"><label class="form-label">Post Code</label><input class="form-control" name="post_code" value="{{ $filters['post_code'] ?? '' }}" placeholder="e.g. 2302"></div><div class="col-md-2"><label class="form-label">Allocation Basis</label><select class="form-select" name="basis"><option value="">All</option>@foreach(['MQ','CFF','EM','PHC'] as $v)<option value="{{ $v }}" @selected(($filters['basis']??'')===$v)>{{ $v }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Allocation</label><select class="form-select" name="allocation"><option value="">All</option><option value="allocated" @selected(($filters['allocation']??'')==='allocated')>Allocated</option><option value="unallocated" @selected(($filters['allocation']??'')==='unallocated')>Unallocated</option></select></div><div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="{{ route('non-cadre.allocation.run',$run->id) }}">Clear</a></div></form></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th class="text-center">Common Merit</th><th>User</th><th>Reg</th><th>Name</th><th class="text-center">Post Code</th><th class="text-center">Choice</th><th class="text-center">Basis</th><th class="text-center">Movement</th></tr></thead><tbody>@forelse($results as $r)<tr><td class="text-center"><strong>{{ $r->common_merit_position }}</strong></td><td>{{ $r->user_id }}</td><td>{{ $r->reg }}</td><td>{{ $r->name }}</td><td class="text-center">{{ $r->post_code ?? '—' }}</td><td class="text-center">{{ $r->choice_position ?? '—' }}</td><td class="text-center">{{ $r->allocation_basis ?? '—' }}</td><td class="text-center">{{ $r->movement_type ?? '—' }}</td></tr>@empty<tr><td colspan="8" class="text-center text-secondary py-4">No candidate matches the selected search/filter.</td></tr>@endforelse</tbody></table></div>
        @if($results->hasPages())<div class="card-footer">{{ $results->links() }}</div>@endif
    </div>
</div>

@if(in_array($run->status,['queued','processing'],true))
<script>
(function(){
 const url=@json(route('non-cadre.allocation.progress',$run->id));
 const tick=async()=>{try{const r=await fetch(url,{headers:{'Accept':'application/json'}});if(!r.ok)return;const d=await r.json();
 document.getElementById('nc4-status').textContent=String(d.status||'').toUpperCase();
 const b=document.getElementById('nc4-queue-badge');if(b)b.textContent=String(d.status||'').toUpperCase();
 const st=document.getElementById('nc4-queue-stage');if(st)st.textContent=d.queue_stage||d.phase||'';
 const bar=document.getElementById('nc4-progress-bar');if(bar){bar.style.width=(d.progress_percent||0)+'%';bar.textContent=(d.progress_percent||0)+'%';}
 if(d.failure_message){const f=document.getElementById('nc4-failure');if(f){f.textContent=d.failure_message;f.classList.remove('d-none');}}
 if(['queued','processing'].includes(d.status)){setTimeout(tick,1500);}else{window.location.reload();}
 }catch(e){setTimeout(tick,3000);}};setTimeout(tick,1000);
})();
</script>
@endif
@endsection
