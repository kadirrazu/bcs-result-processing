@extends('layouts.app')
@section('content')
<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center"><div><h2 class="page-title">Allocation Input Freeze v{{ $freeze->version }}</h2><div class="text-secondary">Immutable direct-input snapshot and deterministic queue preview.</div></div><a class="btn btn-outline-secondary" href="{{ route('allocation.index') }}">Back to Allocation</a></div></div></div>
<div class="page-body"><div class="container-xl">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row row-cards mb-3">
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Status</div><div class="h3 mb-0">{{ strtoupper($freeze->status) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Candidates</div><div class="h3 mb-0">{{ number_format($freeze->total_candidates) }}</div><div class="small text-secondary">Choice ready: {{ number_format($freeze->choice_ready_candidates) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Queue Entries</div><div class="h3 mb-0">{{ number_format($freeze->total_queue_entries) }}</div><div class="small text-secondary">Skipped choices: {{ number_format($freeze->skipped_choice_entries) }}</div></div></div></div>
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary">Choice Source</div><div class="fw-semibold">{{ strtoupper(str_replace('_',' ', $freeze->choice_source)) }}</div><div class="small text-secondary">Frozen {{ $freeze->frozen_at }}</div></div></div></div>
</div>

<div class="card mb-3"><div class="card-header"><h3 class="card-title">Integrity Fingerprints</h3></div><div class="card-body">
    <div class="mb-2"><strong>Input Fingerprint</strong><div class="text-break"><code>{{ $freeze->input_fingerprint }}</code></div></div>
    <div class="mb-2"><strong>Queue Hash</strong><div class="text-break"><code>{{ $freeze->queue_hash }}</code></div></div>
    <div class="row g-2 small mt-2">
        <div class="col-md-6">Registration: <code>{{ $freeze->registration_hash }}</code></div>
        <div class="col-md-6">Circular: <code>{{ $freeze->circular_hash }}</code></div>
        <div class="col-md-6">Choice: <code>{{ $freeze->choice_hash }}</code></div>
        <div class="col-md-6">Merit: <code>{{ $freeze->merit_hash }}</code></div>
        <div class="col-md-6">Settings: <code>{{ $freeze->settings_hash }}</code></div>
        <div class="col-md-6">Seat Breakup: <code>{{ $freeze->seat_breakup_hash }}</code></div>
    </div>
</div></div>

<div class="card mb-3">
    <div class="card-header"><div><h3 class="card-title">Candidate Queue Lookup</h3><div class="card-subtitle">Search by registration/roll to see how many cadre queues a candidate is present in.</div></div></div>
    <div class="card-body">
        <form method="GET" action="{{ route('allocation.input-freeze.show', $freeze) }}" class="row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label">Registration / Roll</label><input type="text" class="form-control" name="reg" value="{{ $reg }}" placeholder="e.g. 47001234"></div>
            <div class="col-md-auto"><button class="btn btn-primary">Search Candidate</button></div>
            @if($reg !== '')<div class="col-md-auto"><a class="btn btn-outline-secondary" href="{{ route('allocation.input-freeze.show', $freeze) }}">Clear</a></div>@endif
        </form>
        @if($candidateSearchSummary !== null)
            <div class="mt-3">
                @forelse($candidateSearchSummary as $row)
                    <div class="alert alert-info py-2 mb-2"><strong>{{ $row['reg'] }}</strong>@if($row['category']) <span class="text-secondary">({{ $row['category'] }})</span>@endif is present in <strong>{{ number_format($row['queue_count']) }}</strong> cadre queue{{ $row['queue_count'] === 1 ? '' : 's' }}.@if(count($row['cadres'])) <span class="ms-2">Cadres: <code>{{ implode(', ', $row['cadres']) }}</code></span>@endif</div>
                @empty
                    <div class="alert alert-warning py-2 mb-0">No frozen candidate matched this registration/roll.</div>
                @endforelse
            </div>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><div><h3 class="card-title">Cadre Queue Summary</h3><div class="card-subtitle">Circular order, queue size and seat breakup are shown once per cadre.</div></div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0">
        <thead><tr><th class="text-center">SL</th><th>Cadre</th><th class="text-center">Type</th><th class="text-center">Queue Entries</th><th class="text-center">Total</th><th class="text-center">MQ</th><th class="text-center">CFF</th><th class="text-center">EM</th><th class="text-center">PHC</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        @foreach($cadreSummaries as $summary)
            @php($entry = $summaryEntries->get($summary->circular_entry_id))
            <tr>
                <td class="text-center text-nowrap">{{ $entry?->cadre_serial ?? '—' }}@if($entry?->sub_serial !== null).{{ $entry->sub_serial }}@endif</td>
                <td><div><code>{{ $summary->cadre_code }}</code></div><div class="small">{{ $entry?->post_name_snapshot ?: $entry?->cadre_name_snapshot ?: '—' }}</div></td>
                <td class="text-center"><span class="badge bg-secondary-lt">{{ $summary->cadre_type }}</span></td>
                <td class="text-center">{{ number_format($summary->queue_count) }}</td>
                <td class="text-center">{{ $summary->total_post }}</td>
                <td class="text-center">{{ $summary->mq }}</td>
                <td class="text-center">{{ $summary->cff }}</td>
                <td class="text-center">{{ $summary->em }}</td>
                <td class="text-center">{{ $summary->phc }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('allocation.input-freeze.cadre-queue', [$freeze, $entry]) }}">View Queue</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header"><div class="w-100">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div><h3 class="card-title">Deterministic Queue Entries</h3><div class="card-subtitle">Filtered queue entries: <strong>{{ number_format($queues->total()) }}</strong>. Candidate appears only for a chosen cadre with an applicable authoritative merit source.</div></div>
            <form method="GET" action="{{ route('allocation.input-freeze.show', $freeze) }}" class="d-flex flex-wrap gap-2 align-items-end">
                <div><label class="form-label mb-1">Registration / Roll</label><input type="text" class="form-control" name="reg" value="{{ $reg }}" placeholder="Search reg"></div>
                <div><label class="form-label mb-1">Cadre</label><select class="form-select" name="cadre_code" style="min-width:280px">
                    <option value="">All Cadres</option>
                    @foreach($cadreSummaries as $summary)
                        @php($entry = $summaryEntries->get($summary->circular_entry_id))
                        <option value="{{ $summary->cadre_code }}" @selected((string)$cadreCode === (string)$summary->cadre_code)>
                            {{ $summary->cadre_code }} — {{ $entry?->post_name_snapshot ?: $entry?->cadre_name_snapshot ?: 'Cadre' }}
                        </option>
                    @endforeach
                </select></div>
                <div><button class="btn btn-primary">Filter</button></div>
                @if($cadreCode !== '' || $reg !== '')<div><a class="btn btn-outline-secondary" href="{{ route('allocation.input-freeze.show', $freeze) }}">Clear</a></div>@endif
            </form>
        </div>
    </div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th class="text-center">SL</th><th>Cadre</th><th>Type</th><th>Merit Pos.</th><th>Reg / Candidate</th><th>Choice Pos.</th><th>Merit Source</th><th>Quota</th></tr></thead><tbody>
    @forelse($queues as $q)
    @php($candidate = $candidateMap->get($q->registration_id))
    <tr>
        <td class="text-center">{{ ($queues->firstItem() ?? 1) + $loop->index }}</td>
        <td><div><code>{{ $q->cadre_code }}</code></div><div class="small">{{ $q->circularEntry?->post_name_snapshot ?: $q->circularEntry?->cadre_name_snapshot ?: '—' }}</div></td>
        <td><span class="badge bg-secondary-lt">{{ $q->cadre_type }}</span></td>
        <td><strong>{{ $q->merit_position }}</strong></td>
        <td><div><code>{{ $candidate?->reg ?: '—' }}</code></div><div class="small text-secondary">{{ $candidate?->cadre_category ?: '—' }}</div></td>
        <td>#{{ str_pad((string)$q->choice_position,2,'0',STR_PAD_LEFT) }}</td>
        <td><code>{{ $q->merit_source }}</code></td>
        <td>
            @if($q->eligible_cff)<span class="badge bg-azure-lt me-1">CFF</span>@endif
            @if($q->eligible_em)<span class="badge bg-azure-lt me-1">EM</span>@endif
            @if($q->eligible_phc)<span class="badge bg-azure-lt">PHC</span>@endif
        </td>
    </tr>
    @empty<tr><td colspan="8" class="text-center text-secondary py-4">No queue entries match the selected filter.</td></tr>@endforelse
    </tbody></table></div>
    @if($queues->hasPages())<div class="card-footer">{{ $queues->links() }}</div>@endif
</div>
</div></div>
@endsection
