@extends('layouts.app')
@section('content')
<div class="page-header"><div class="container-xl"><div class="d-flex justify-content-between align-items-center gap-2"><div><h2 class="page-title">Cadre Queue — {{ $circularEntry->effective_code }}</h2><div class="text-secondary">Allocation Input Freeze v{{ $freeze->version }} · Serial {{ $circularEntry->cadre_serial }}@if($circularEntry->sub_serial !== null).{{ $circularEntry->sub_serial }}@endif · {{ $circularEntry->post_name_snapshot ?: $circularEntry->cadre_name_snapshot }}</div></div><a class="btn btn-outline-secondary" href="{{ route('allocation.input-freeze.show', $freeze) }}">Back to Frozen Input</a></div></div></div>
<div class="page-body"><div class="container-xl">
<div class="row row-cards mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-secondary">Full Queue Size</div><div class="h3 mb-0">{{ number_format($queueTotal) }}</div></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-secondary">Filtered Entries</div><div class="h3 mb-0">{{ number_format($queues->total()) }}</div></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-secondary">Cadre Type</div><div class="h3 mb-0">{{ $circularEntry->cadre_type->value ?? $circularEntry->cadre_type }}</div></div></div></div>
</div>
<div class="card">
    <div class="card-header"><div class="w-100"><div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div><h3 class="card-title">Deterministic Cadre Queue</h3><div class="card-subtitle">Ordered by authoritative merit position with stable identity fallback.</div></div>
        <form method="GET" action="{{ route('allocation.input-freeze.cadre-queue', [$freeze, $circularEntry]) }}" class="d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label mb-1">Registration / Roll</label><input class="form-control" type="text" name="reg" value="{{ $reg }}" placeholder="Search reg"></div>
            <div><label class="form-label mb-1">Quota</label><select class="form-select" name="quota"><option value="">All</option><option value="CFF" @selected($quota==='CFF')>CFF</option><option value="EM" @selected($quota==='EM')>EM</option><option value="PHC" @selected($quota==='PHC')>PHC</option></select></div>
            <div><button class="btn btn-primary">Filter</button></div>
            @if($reg !== '' || $quota !== '')<div><a class="btn btn-outline-secondary" href="{{ route('allocation.input-freeze.cadre-queue', [$freeze, $circularEntry]) }}">Clear</a></div>@endif
        </form>
    </div></div></div>
    <div class="table-responsive"><table class="table table-vcenter mb-0"><thead><tr><th class="text-center">SL</th><th class="text-center">Merit Pos.</th><th>Reg / Candidate</th><th class="text-center">Choice Pos.</th><th>Merit Source</th><th>Quota</th></tr></thead><tbody>
    @forelse($queues as $q)
        @php($candidate = $candidateMap->get($q->registration_id))
        <tr>
            <td class="text-center">{{ ($queues->firstItem() ?? 1) + $loop->index }}</td>
            <td class="text-center fw-semibold">{{ $q->merit_position }}</td>
            <td><div><code>{{ $candidate?->reg ?: '—' }}</code></div><div class="small text-secondary">{{ $candidate?->cadre_category ?: '—' }}</div></td>
            <td class="text-center">#{{ str_pad((string)$q->choice_position,2,'0',STR_PAD_LEFT) }}</td>
            <td><code>{{ $q->merit_source }}</code></td>
            <td>@if($q->eligible_cff)<span class="badge bg-azure-lt me-1">CFF</span>@endif @if($q->eligible_em)<span class="badge bg-azure-lt me-1">EM</span>@endif @if($q->eligible_phc)<span class="badge bg-azure-lt">PHC</span>@endif</td>
        </tr>
    @empty<tr><td colspan="6" class="text-center text-secondary py-4">No queue entries match the selected filter.</td></tr>@endforelse
    </tbody></table></div>
    @if($queues->hasPages())<div class="card-footer">{{ $queues->links() }}</div>@endif
</div>
</div></div>
@endsection
