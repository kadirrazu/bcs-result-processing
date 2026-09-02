@extends('layouts.app')
@section('content')
<style>
    /* A3 review tables deliberately keep all operational numeric/status fields centered. */
    .a3-seat-ledger thead th,
    .a3-results-table thead th { font-weight: 700; }
    .a3-seat-ledger th,
    .a3-seat-ledger td,
    .a3-results-table th,
    .a3-results-table td { text-align: center; vertical-align: middle; }
    /* Bucket cells stay compact but their three value lines are easier to scan left-to-right. */
    .a3-seat-ledger .a3-bucket-cell { text-align: left; vertical-align: middle; }
    .a3-bucket-line { white-space: nowrap; line-height: 1.35; }
</style>

<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-3"><div><h2 class="page-title">Allocation Phase-1 — Run v{{ $run->version }}</h2><div class="text-secondary">Frozen MQ + quota output only. NM conversion and shifting have not yet run.</div></div><a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a></div></div></div>
<div class="page-body"><div class="container-xl">

<div class="row row-cards mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Allocated</div><div class="h2 mb-0">{{ number_format($run->allocated_count) }}</div><div class="small text-secondary">Unallocated {{ number_format($run->unallocated_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Basis</div><div class="fw-semibold">MQ {{ number_format($run->mq_count) }}</div><div class="small">CFF {{ number_format($run->cff_count) }} · EM {{ number_format($run->em_count) }} · PHC {{ number_format($run->phc_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Decision Status</div><div class="fw-semibold">FINAL {{ number_format($run->final_count) }}</div><div class="small">TEMPORARY {{ number_format($run->temporary_count) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Fixed Point</div><div class="h2 mb-0">{{ number_format($run->iteration_count) }}</div><div class="small text-secondary">iterations</div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><div><h3 class="card-title">Phase-1 Seat Ledger</h3><div class="card-subtitle">Occupied and remaining original MQ/CFF/EM/PHC buckets. Remaining quota is not converted in A3.</div></div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a3-seat-ledger">
        <thead><tr>
            <th>SL</th>
            <th>Cadre Abbreviation</th>
            <th>Cadre Code</th>
            <th>Total Post</th>
            <th>Allocated Post</th>
            <th>Remain Post</th>
            <th>MQ</th>
            <th>CFF</th>
            <th>EM</th>
            <th>PHC</th>
        </tr></thead>
        <tbody>
        @foreach($ledgers as $ledger)
            @php
                $entry = $ledger->circularEntry;
                $serial = $entry
                    ? ((string) $entry->cadre_serial . ($entry->sub_serial !== null ? '.'.(string) $entry->sub_serial : ''))
                    : '—';
                // The controller builds one code=>abbreviation lookup from Cadre/Sub-Cadre masters.
                // Reusing it here keeps the ledger compact and uses the same abbreviation shown in
                // Phase-1 Candidate Results for the identical cadre code.
                $abbreviation = $abbreviationByCode->get((int) $ledger->cadre_code, '—');
                $allocated = (int)$ledger->mq_occupied + (int)$ledger->cff_occupied + (int)$ledger->em_occupied + (int)$ledger->phc_occupied;
                $remaining = (int)$ledger->mq_remaining + (int)$ledger->cff_remaining + (int)$ledger->em_remaining + (int)$ledger->phc_remaining;

                // Conditional colours deliberately show capacity health instead of decorating all values:
                // fully occupied => green, partially occupied => warning; zero remaining => normal black,
                // any remaining capacity => red. The same rule is applied to every quota bucket below.
                $allocatedClass = $allocated === (int)$ledger->total_capacity ? 'text-success' : 'text-warning';
                $remainingClass = $remaining === 0 ? 'text-body' : 'text-danger';
            @endphp
            <tr>
                <td>{{ $serial }}</td>
                <td><strong>{{ $abbreviation }}</strong></td>
                <td><strong>{{ $ledger->cadre_code }}</strong></td>
                <td><span class="text-body">{{ number_format($ledger->total_capacity) }}</span></td>
                <td><span class="{{ $allocatedClass }} fw-semibold">{{ number_format($allocated) }}</span></td>
                <td><span class="{{ $remainingClass }} fw-semibold">{{ number_format($remaining) }}</span></td>
                @foreach([
                    ['total' => $ledger->mq_capacity, 'allocated' => $ledger->mq_occupied, 'remain' => $ledger->mq_remaining],
                    ['total' => $ledger->cff_capacity, 'allocated' => $ledger->cff_occupied, 'remain' => $ledger->cff_remaining],
                    ['total' => $ledger->em_capacity, 'allocated' => $ledger->em_occupied, 'remain' => $ledger->em_remaining],
                    ['total' => $ledger->phc_capacity, 'allocated' => $ledger->phc_occupied, 'remain' => $ledger->phc_remaining],
                ] as $bucket)
                    @php
                        $bucketAllocatedClass = (int)$bucket['allocated'] === (int)$bucket['total'] ? 'text-success' : 'text-warning';
                        $bucketRemainClass = (int)$bucket['remain'] === 0 ? 'text-body' : 'text-danger';
                    @endphp
                    <td class="a3-bucket-cell">
                        <div class="a3-bucket-line text-body">Total: {{ number_format($bucket['total']) }}</div>
                        <div class="a3-bucket-line {{ $bucketAllocatedClass }}">Allocated: {{ number_format($bucket['allocated']) }}</div>
                        <div class="a3-bucket-line {{ $bucketRemainClass }}">Remain: {{ number_format($bucket['remain']) }}</div>
                    </td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header"><div><h3 class="card-title">Phase-1 Candidate Results</h3><div class="card-subtitle">FINAL here means first-choice MQ only. Every other A3 assignment remains TEMPORARY until A4 reaches the global post-conversion fixed point.</div></div></div>
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Registration / Roll</label><input class="form-control" name="reg" value="{{ $reg }}"></div>
            <div class="col-md-3"><label class="form-label">Cadre Code</label><select class="form-select" name="cadre_code"><option value="">All Cadres</option>@foreach($cadreOptions as $option)<option value="{{ $option['code'] }}" @selected((string)$cadreCode === (string)$option['code'])>{{ $option['code'] }}@if($option['title'] !== '') — {{ $option['title'] }}@endif</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Basis</label><select class="form-select" name="basis"><option value="">All</option>@foreach(['MQ','CFF','EM','PHC'] as $q)<option value="{{ $q }}" @selected($basis===$q)>{{ $q }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="decision_status"><option value="">All</option><option value="FINAL" @selected($decisionStatus==='FINAL')>FINAL</option><option value="TEMPORARY" @selected($decisionStatus==='TEMPORARY')>TEMPORARY</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('allocation.runs.show',$run) }}">Reset</a></div>
        </form>
        <div class="small text-secondary mt-3">Filtered results: {{ number_format($results->total()) }}</div>
    </div>
    <div class="table-responsive"><table class="table table-vcenter mb-0 a3-results-table">
        <thead><tr><th>SL</th><th>Reg</th><th>Cadre Code</th><th>Cadre Abbreviation</th><th>Choice</th><th>Merit</th><th>Source</th><th>Basis</th><th>Movement</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($results as $row)
            <tr>
                <td>{{ $results->firstItem() + $loop->index }}</td>
                <td><strong>{{ $row->reg }}</strong></td>
                <td><strong>{{ $row->cadre_code }}</strong></td>
                <td><strong>{{ $abbreviationByCode->get((int)$row->cadre_code, '—') }}</strong></td>
                <td>#{{ str_pad((string)$row->choice_position, 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ number_format($row->merit_position) }}</td>
                <td><code>{{ $row->merit_source }}</code></td>
                <td><span class="badge bg-{{ $row->allocation_basis === 'MQ' ? 'azure' : 'purple' }}-lt">{{ $row->allocation_basis }}</span></td>
                <td>{{ $row->movement_type }}</td>
                <td><span class="badge bg-{{ $row->decision_status === 'FINAL' ? 'success' : 'warning' }}-lt">{{ $row->decision_status }}</span></td>
            </tr>
        @empty<tr><td colspan="10" class="text-center text-secondary py-4">No matching Phase-1 allocation result.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($results->hasPages())<div class="card-footer">{{ $results->links() }}</div>@endif
</div>

<div class="mt-3 small text-secondary text-break">Phase-1 output hash: <code>{{ $run->phase1_output_hash ?: '—' }}</code><br>Seat ledger hash: <code>{{ $run->seat_ledger_hash ?: '—' }}</code></div>
</div></div>
@endsection
