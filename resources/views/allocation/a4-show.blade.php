@extends('layouts.app')
@section('content')
@if((bool)$a4Run->is_stale)
<div class="container-xl mt-3">
    <div class="alert alert-warning mb-0"><strong>A4 Phase-2 — STALE / OUTDATED.</strong> {{ $a4Run->stale_reason ?: 'Authoritative Allocation inputs/source changed after this result was produced.' }}</div>
</div>
@endif

<style>
    /* A4 ledger mirrors the compact A3 seat-ledger visual contract. */
    .a4-seat-ledger .a4-column-header th{font-weight:700}
    .a4-seat-ledger th,.a4-seat-ledger td{text-align:center;vertical-align:middle}
    .a4-seat-ledger .a4-bucket-cell{text-align:left;vertical-align:middle}
    .a4-bucket-line{white-space:nowrap;line-height:1.25;font-size:.82rem}
    .a4-seat-ledger th,.a4-seat-ledger td{padding:.45rem .5rem}
    .a4-group-row td{text-align:left!important;font-weight:700;background:var(--tblr-bg-surface-secondary)}
.a4-seat-ledger .a4-cadre-cell{text-align:left!important;vertical-align:middle}
</style>

<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div>
        <h2 class="page-title">Allocation A4 — Phase-2 Seat Ledger · Run v{{ $a4Run->version }}</h2>
        <div class="text-secondary">Circular group/serial order. A3 Run v{{ $a4Run->phase1Run?->version }} remains immutable source evidence.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="{{ route('allocation.a4.candidates',$a4Run) }}">A4 Candidate Results</a>
        <a class="btn btn-outline-primary" href="{{ route('allocation.runs.show',$a4Run->phase1_run_id) }}">View Original A3</a>
        <a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a>
    </div>
</div></div></div>

<div class="page-body"><div class="container-xl">
<div class="row row-cards mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Allocated</div><div class="h2 mb-0">{{ number_format($a4Run->allocated_count) }}</div><div class="small text-secondary">Unallocated {{ number_format($a4Run->unallocated_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Basis</div><div class="fw-semibold">MQ {{ number_format($a4Run->mq_count) }}</div><div class="small">CFF {{ number_format($a4Run->cff_count) }} · EM {{ number_format($a4Run->em_count) }} · PHC {{ number_format($a4Run->phc_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Movement</div><div class="fw-semibold">NM {{ number_format($a4Run->nm_count) }} · SHIFTED {{ number_format($a4Run->shifted_count) }}</div><div class="small text-secondary">Quota→Merit {{ number_format($a4Run->quota_to_merit_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Fixed Point</div><div class="h2 mb-0">{{ number_format($a4Run->iteration_count) }}</div><div class="small text-secondary">iterations</div></div></div></div>
</div>
<div class="card">
    <div class="card-header"><div>
        <h3 class="card-title">A4 Seat Ledger</h3>
        <div class="card-subtitle">Click the Cadre field to open the candidate list for that exact Circular cadre/sub-cadre entry.</div>
    </div></div>

    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Cadre Code / Cadre Abbreviation</label>
                <input class="form-control" name="ledger_search" value="{{ $ledgerSearch }}" placeholder="e.g. 110 or ADMN">
            </div>
            <div class="col-md-4">
                <label class="form-label">Cadre Filter</label>
                <select class="form-select" name="ledger_cadre_code">
                    <option value="">All Cadres</option>
                    @foreach($ledgerCadreOptions as $option)
                        <option value="{{ $option['code'] }}" @selected((string)$ledgerCadreCode === (string)$option['code'])>{{ $option['code'] }}@if($option['abbr'] !== '') - {{ $option['abbr'] }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary">Filter</button>
                <a class="btn btn-outline-secondary" href="{{ route('allocation.a4.show',$a4Run) }}">Reset</a>
            </div>
        </form>
        <div class="small text-secondary mt-3">Filtered ledger rows: {{ number_format($ledgers->count()) }}</div>
    </div>

    <div class="table-responsive"><table class="table table-vcenter mb-0 a4-seat-ledger"><tbody>
    @php
        $lastGroup = null;
        $groupTotals = [
            'total_capacity' => 0,
            'total_occupied' => 0,
            'total_remaining' => 0,
            'mq_capacity' => 0, 'mq_occupied' => 0, 'mq_converted' => 0,
            'cff_capacity' => 0, 'cff_occupied' => 0, 'cff_converted' => 0,
            'em_capacity' => 0, 'em_occupied' => 0, 'em_converted' => 0,
            'phc_capacity' => 0, 'phc_occupied' => 0, 'phc_converted' => 0,
            'nm_count' => 0,
            'shifted_count' => 0,
        ];
    @endphp
    @forelse($ledgers as $ledger)
        @php
            $entry=$ledger->circularEntry;
            $type=(string)($entry?->cadre_type?->value ?? $entry?->cadre_type ?? '');
            $groupLabel=$type==='GG'?'General Cadre':($type==='TT'?'Technical / Professional Cadre':'Other');
            $serial=$entry ? ((string)$entry->cadre_serial.($entry->sub_serial!==null?'.'.(string)$entry->sub_serial:'')) : '—';
            $allocated=(int)$ledger->total_occupied; $remaining=(int)$ledger->total_remaining;
            $allocatedClass=$allocated===(int)$ledger->total_capacity?'text-success':'text-warning';
            $remainingClass=$remaining===0?'text-body':'text-danger';
            $abbr=(string)$abbreviationByCode->get((int)$ledger->cadre_code,'—');
        @endphp
        @if($lastGroup !== $groupLabel)
            @if($lastGroup !== null)
                <tr class="fw-bold bg-light">
                    <td></td>
                    <td class="text-start">{{ $lastGroup }} Total</td>
                    <td>{{ number_format($groupTotals['total_capacity']) }}</td>
                    <td>{{ number_format($groupTotals['total_occupied']) }}</td>
                    <td>{{ number_format($groupTotals['total_remaining']) }}</td>
                    @foreach(['mq','cff','em','phc'] as $bucketKey)
                        @php
                            $bucketTotal = (int)$groupTotals[$bucketKey.'_capacity'];
                            $bucketAllocated = (int)$groupTotals[$bucketKey.'_occupied'];
                            $bucketConverted = (int)$groupTotals[$bucketKey.'_converted'];
                            $bucketRemain = $bucketTotal - $bucketAllocated - $bucketConverted;
                        @endphp
                        <td class="a4-bucket-cell">
                            <div class="a4-bucket-line text-body">Total: {{ number_format($bucketTotal) }}</div>
                            <div class="a4-bucket-line">Allocated: {{ number_format($bucketAllocated) }}</div>
                            @if($bucketConverted > 0)<div class="a4-bucket-line text-azure">Converted: {{ number_format($bucketConverted) }}</div>@endif
                            <div class="a4-bucket-line">Remain: {{ number_format($bucketRemain) }}</div>
                        </td>
                    @endforeach
                    <td>{{ number_format($groupTotals['nm_count']) }}</td>
                    <td>{{ number_format($groupTotals['shifted_count']) }}</td>
                </tr>
            @endif
            {{-- Repeat the column heading after every Circular group tagline so the
                 Technical section never starts without its own heading. --}}
            <tr class="a4-group-row"><td colspan="11">{{ $groupLabel }}</td></tr>
            <tr class="a4-column-header">
                <th>SL</th><th class="text-start">Cadre</th><th>Total Post</th><th>Allocated Post</th><th>Remain Post</th>
                <th>MQ</th><th>CFF</th><th>EM</th><th>PHC</th><th>NM</th><th>SHIFTED</th>
            </tr>
            @php
                $lastGroup = $groupLabel;
                $groupTotals = array_fill_keys(array_keys($groupTotals), 0);
            @endphp
        @endif
        @php
            $groupTotals['total_capacity'] += (int)$ledger->total_capacity;
            $groupTotals['total_occupied'] += (int)$ledger->total_occupied;
            $groupTotals['total_remaining'] += (int)$ledger->total_remaining;
            $groupTotals['mq_capacity'] += (int)$ledger->mq_capacity;
            $groupTotals['mq_occupied'] += (int)$ledger->mq_occupied;
            $groupTotals['cff_capacity'] += (int)$ledger->cff_capacity;
            $groupTotals['cff_occupied'] += (int)$ledger->cff_occupied;
            $groupTotals['cff_converted'] += (int)$ledger->converted_cff;
            $groupTotals['em_capacity'] += (int)$ledger->em_capacity;
            $groupTotals['em_occupied'] += (int)$ledger->em_occupied;
            $groupTotals['em_converted'] += (int)$ledger->converted_em;
            $groupTotals['phc_capacity'] += (int)$ledger->phc_capacity;
            $groupTotals['phc_occupied'] += (int)$ledger->phc_occupied;
            $groupTotals['phc_converted'] += (int)$ledger->converted_phc;
            $groupTotals['nm_count'] += (int)$ledger->nm_count;
            $groupTotals['shifted_count'] += (int)$ledger->shifted_count;
        @endphp
        <tr>
            <td>{{ $serial }}</td>
            <td class="a4-cadre-cell"><a class="fw-bold text-decoration-none" href="{{ route('allocation.a4.cadre-results',[$a4Run,$entry]) }}">{{ $ledger->cadre_code }} - {{ $abbr }}</a></td>
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
            <td><strong>{{ number_format($ledger->nm_count) }}</strong></td>
            <td><strong>{{ number_format($ledger->shifted_count) }}</strong></td>
        </tr>
    @empty
        <tr><td colspan="11" class="text-center text-secondary py-4">No matching A4 Seat Ledger row.</td></tr>
    @endforelse
    @if($lastGroup !== null)
        <tr class="fw-bold bg-light">
            <td></td>
            <td class="text-start">{{ $lastGroup }} Total</td>
            <td>{{ number_format($groupTotals['total_capacity']) }}</td>
            <td>{{ number_format($groupTotals['total_occupied']) }}</td>
            <td>{{ number_format($groupTotals['total_remaining']) }}</td>
            @foreach(['mq','cff','em','phc'] as $bucketKey)
                @php
                    $bucketTotal = (int)$groupTotals[$bucketKey.'_capacity'];
                    $bucketAllocated = (int)$groupTotals[$bucketKey.'_occupied'];
                    $bucketConverted = (int)$groupTotals[$bucketKey.'_converted'];
                    $bucketRemain = $bucketTotal - $bucketAllocated - $bucketConverted;
                @endphp
                <td class="a4-bucket-cell">
                    <div class="a4-bucket-line text-body">Total: {{ number_format($bucketTotal) }}</div>
                    <div class="a4-bucket-line">Allocated: {{ number_format($bucketAllocated) }}</div>
                    @if($bucketConverted > 0)<div class="a4-bucket-line text-azure">Converted: {{ number_format($bucketConverted) }}</div>@endif
                    <div class="a4-bucket-line">Remain: {{ number_format($bucketRemain) }}</div>
                </td>
            @endforeach
            <td>{{ number_format($groupTotals['nm_count']) }}</td>
            <td>{{ number_format($groupTotals['shifted_count']) }}</td>
        </tr>
    @endif
    </tbody></table></div>
</div>
</div></div>
@endsection
