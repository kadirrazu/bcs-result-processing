@extends('layouts.app')
@section('content')
<style>
    /* A4 deliberately mirrors A3 review alignment and conditional capacity colours. */
    .a4-seat-ledger thead th,.a4-results-table thead th,.a4-movement-table thead th{font-weight:700}
    .a4-seat-ledger th,.a4-seat-ledger td,.a4-results-table th,.a4-results-table td,.a4-movement-table th,.a4-movement-table td{text-align:center;vertical-align:middle}
    .a4-seat-ledger .a4-bucket-cell{text-align:left;vertical-align:middle}
    .a4-bucket-line{white-space:nowrap;line-height:1.35}
</style>

<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-3">
    <div><h2 class="page-title">Allocation A4 — NM / Shifting Run v{{ $a4Run->version }}</h2><div class="text-secondary">Monotonic merit-only improvement from immutable A3 Run v{{ $a4Run->phase1Run?->version }}. Quota priority/entitlement is inactive in A4.</div></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="{{ route('allocation.runs.show',$a4Run->phase1_run_id) }}">View Original A3</a><a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a></div>
</div></div></div>
<div class="page-body"><div class="container-xl">

<div class="row row-cards mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Allocated</div><div class="h2 mb-0">{{ number_format($a4Run->allocated_count) }}</div><div class="small text-secondary">Unallocated {{ number_format($a4Run->unallocated_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Final Basis</div><div class="fw-semibold">MQ {{ number_format($a4Run->mq_count) }}</div><div class="small">CFF {{ number_format($a4Run->cff_count) }} · EM {{ number_format($a4Run->em_count) }} · PHC {{ number_format($a4Run->phc_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Movement</div><div class="fw-semibold">NM {{ number_format($a4Run->nm_count) }}</div><div class="small">SHIFTED {{ number_format($a4Run->shifted_count) }} · Quota→Merit {{ number_format($a4Run->quota_to_merit_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Global Fixed Point</div><div class="h2 mb-0">{{ number_format($a4Run->iteration_count) }}</div><div class="small text-secondary">iterations</div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><div><h3 class="card-title">A4 Seat Ledger</h3><div class="card-subtitle">A3 seats are preserved as source evidence. Vacant/released quota seats shown as converted capacity are merit/NM only.</div></div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a4-seat-ledger"><thead><tr>
        <th>SL</th><th>Cadre Abbreviation</th><th>Cadre Code</th><th>Total Post</th><th>Allocated Post</th><th>Remain Post</th><th>MQ</th><th>CFF</th><th>EM</th><th>PHC</th><th>NM</th><th>SHIFTED</th>
    </tr></thead><tbody>
    @foreach($ledgers as $ledger)
        @php
            $entry=$ledger->circularEntry;
            $serial=$entry ? ((string)$entry->cadre_serial.($entry->sub_serial!==null?'.'.(string)$entry->sub_serial:'')) : '—';
            $allocated=(int)$ledger->total_occupied; $remaining=(int)$ledger->total_remaining;
            $allocatedClass=$allocated===(int)$ledger->total_capacity?'text-success':'text-warning';
            $remainingClass=$remaining===0?'text-body':'text-danger';
        @endphp
        <tr>
            <td>{{ $serial }}</td><td><strong>{{ $abbreviationByCode->get((int)$ledger->cadre_code,'—') }}</strong></td><td><strong>{{ $ledger->cadre_code }}</strong></td>
            <td><span class="text-body">{{ number_format($ledger->total_capacity) }}</span></td>
            <td><span class="{{ $allocatedClass }} fw-semibold">{{ number_format($allocated) }}</span></td>
            <td><span class="{{ $remainingClass }} fw-semibold">{{ number_format($remaining) }}</span></td>
            @foreach([
                ['total'=>$ledger->mq_capacity,'allocated'=>$ledger->mq_occupied,'converted'=>0],
                ['total'=>$ledger->cff_capacity,'allocated'=>$ledger->cff_occupied,'converted'=>$ledger->converted_cff],
                ['total'=>$ledger->em_capacity,'allocated'=>$ledger->em_occupied,'converted'=>$ledger->converted_em],
                ['total'=>$ledger->phc_capacity,'allocated'=>$ledger->phc_occupied,'converted'=>$ledger->converted_phc],
            ] as $bucket)
                @php
                    $bucketRemain=(int)$bucket['total']-(int)$bucket['allocated']-(int)$bucket['converted'];
                    $bucketAllocatedClass=(int)$bucket['allocated']===(int)$bucket['total']?'text-success':'text-warning';
                    $bucketRemainClass=$bucketRemain===0?'text-body':'text-danger';
                @endphp
                <td class="a4-bucket-cell">
                    <div class="a4-bucket-line text-body">Total: {{ number_format($bucket['total']) }}</div>
                    <div class="a4-bucket-line {{ $bucketAllocatedClass }}">Allocated: {{ number_format($bucket['allocated']) }}</div>
                    @if((int)$bucket['converted']>0)<div class="a4-bucket-line text-azure">Converted: {{ number_format($bucket['converted']) }}</div>@endif
                    <div class="a4-bucket-line {{ $bucketRemainClass }}">Remain: {{ number_format($bucketRemain) }}</div>
                </td>
            @endforeach
            <td><strong>{{ number_format($ledger->nm_count) }}</strong></td><td><strong>{{ number_format($ledger->shifted_count) }}</strong></td>
        </tr>
    @endforeach
    </tbody></table></div>
</div>

<div class="card mb-3">
    <div class="card-header"><div><h3 class="card-title">A4 Candidate Results</h3><div class="card-subtitle">All A4 selections are target-merit based. Retained A3 quota basis remains quota only until its seat is released or same-cadre normalized.</div></div></div>
    <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Registration / Roll</label><input class="form-control" name="reg" value="{{ $reg }}"></div>
        <div class="col-md-3"><label class="form-label">Cadre Code</label><select class="form-select" name="cadre_code"><option value="">All Cadres</option>@foreach($cadreOptions as $option)<option value="{{ $option['code'] }}" @selected((string)$cadreCode===(string)$option['code'])>{{ $option['code'] }}@if($option['title']!=='') — {{ $option['title'] }}@endif</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Basis</label><select class="form-select" name="basis"><option value="">All</option>@foreach(['MQ','CFF','EM','PHC'] as $q)<option value="{{ $q }}" @selected($basis===$q)>{{ $q }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Movement</label><select class="form-select" name="movement"><option value="">All</option>@foreach(['DIRECT','NM','SHIFTED'] as $m)<option value="{{ $m }}" @selected($movement===$m)>{{ $m }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('allocation.a4.show',$a4Run) }}">Reset</a></div>
    </form><div class="small text-secondary mt-3">Filtered results: {{ number_format($results->total()) }}</div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a4-results-table"><thead><tr>
        <th>SL</th><th>Reg</th><th>Cadre Code</th><th>Cadre Abbreviation</th><th>Choice</th><th>Merit</th><th>Basis</th><th>Movement</th><th>Original Cadre</th><th>Original Choice</th><th>Reason</th>
    </tr></thead><tbody>
    @forelse($results as $row)<tr>
        <td>{{ $results->firstItem()+$loop->index }}</td><td><strong>{{ $row->reg }}</strong></td><td><strong>{{ $row->cadre_code }}</strong></td><td><strong>{{ $abbreviationByCode->get((int)$row->cadre_code,'—') }}</strong></td>
        <td>#{{ str_pad((string)$row->choice_position,2,'0',STR_PAD_LEFT) }}</td><td>{{ number_format($row->merit_position) }}</td><td><span class="badge bg-{{ $row->allocation_basis==='MQ'?'azure':'purple' }}-lt">{{ $row->allocation_basis }}</span></td>
        <td><span class="badge bg-{{ $row->movement_type==='SHIFTED'?'orange':($row->movement_type==='NM'?'blue':'secondary') }}-lt">{{ $row->movement_type }}</span></td>
        <td>{{ $row->original_cadre_code ?: '—' }}@if($row->original_allocation_basis) <span class="text-secondary">({{ $row->original_allocation_basis }})</span>@endif</td><td>{{ $row->original_choice_position ? '#'.str_pad((string)$row->original_choice_position,2,'0',STR_PAD_LEFT) : '—' }}</td><td><code>{{ $row->decision_reason }}</code></td>
    </tr>@empty<tr><td colspan="11" class="text-center text-secondary py-4">No matching A4 result.</td></tr>@endforelse
    </tbody></table></div>@if($results->hasPages())<div class="card-footer">{{ $results->links() }}</div>@endif
</div>

<div class="card mb-3">
    <div class="card-header"><div><h3 class="card-title">Recent A4 Movement Audit</h3><div class="card-subtitle">Persistent movement evidence. Full history remains stored even when this review shows only the latest 100 candidate events.</div></div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a4-movement-table"><thead><tr><th>Seq</th><th>Iteration</th><th>Actor</th><th>Reg ID</th><th>Event</th><th>From</th><th>To</th><th>Merit</th><th>Movement</th><th>Reason</th><th>Converted From</th></tr></thead><tbody>
    @forelse($movements as $event)<tr><td>{{ $event->sequence_no }}</td><td>{{ $event->iteration_no }}</td><td>{{ $event->actor_id ?: '—' }}</td><td>{{ $event->registration_id }}</td><td>{{ $event->event }}</td><td>{{ $event->from_cadre_code ?: '—' }}@if($event->from_basis) / {{ $event->from_basis }}@endif</td><td>{{ $event->to_cadre_code ?: '—' }}@if($event->to_basis) / {{ $event->to_basis }}@endif</td><td>{{ $event->target_merit_position ? number_format($event->target_merit_position) : '—' }}</td><td>{{ $event->movement_type ?: '—' }}</td><td><code>{{ $event->reason }}</code></td><td>{{ $event->converted_from ?: '—' }}</td></tr>
    @empty<tr><td colspan="11" class="text-center text-secondary py-4">No candidate movement event.</td></tr>@endforelse
    </tbody></table></div>
</div>

<div class="small text-secondary text-break">A3 source hash: <code>{{ $a4Run->phase1_output_hash }}</code><br>A4 output hash: <code>{{ $a4Run->a4_output_hash }}</code><br>A4 seat ledger hash: <code>{{ $a4Run->seat_ledger_hash }}</code><br>Movement hash: <code>{{ $a4Run->movement_hash }}</code></div>
</div></div>
@endsection
