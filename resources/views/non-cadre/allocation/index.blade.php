@extends('layouts.app')
@section('content')
<div class="container-xl">
    <div class="page-header mb-4"><div class="row align-items-center"><div class="col"><div class="page-pretitle">Non-Cadre Processing · NC4</div><h2 class="page-title">NC4 — Non-Cadre Allocation</h2><div class="text-secondary mt-1">Input Freeze → Phase-1 MQ + Quota → Phase-2 Shifting + NM → Validation → Finalization</div></div></div></div>
    @if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger mb-4">{{ $errors->first() }}</div>@endif

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Processing Status Board</h3></div>
        <div class="card-body"><div class="row g-3">
            @foreach($statusBoard as $step)
                @php($ok=in_array($step['status'],['READY','COMPLETED','FINALIZED'],true))
                @php($bad=in_array($step['status'],['BLOCKED','FAILED'],true))
                <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-3 h-100"><div class="text-secondary small">{{ $step['label'] }}</div><div class="mt-2"><span class="badge {{ $ok?'bg-green-lt':($bad?'bg-red-lt':'bg-blue-lt') }}">{{ $step['status'] }}</span></div></div></div>
            @endforeach
        </div></div>
    </div>

    <div class="card mb-4">
        <div class="card-body"><div class="d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><div class="text-secondary">NC4 Readiness</div><div class="mt-1">@if($prerequisites['ready'])<span class="badge bg-green-lt">READY</span>@else<span class="badge bg-red-lt">BLOCKED</span><span class="text-secondary ms-2">{{ $prerequisites['reason'] }}</span>@endif</div></div>@if($prerequisites['ready'])<form method="post" action="{{ route('non-cadre.allocation.freeze') }}">@csrf<button class="btn btn-primary">NC4.1 — Freeze Allocation Input</button></form>@endif</div></div>
    </div>

    @if($population)
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Candidate Population Breakdown — Latest Input Freeze</h3></div>
        <div class="card-body"><div class="row g-3">
            @foreach([
                ['Base / Frozen Source Population',$population['source_population'],'secondary'],
                ['Previous BCS Positive',$population['previous_bcs_positive'],'orange'],
                ['Google Form Positive',$population['google_form_positive'],'azure'],
                ['Positive in Both Sources',$population['both_sources_positive'],'purple'],
                ['Total Historically Excluded',$population['historically_excluded'],'red'],
                ['Final Allocation Eligible',$population['allocation_eligible'],'green'],
            ] as [$label,$value,$color])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="border rounded p-3 h-100 text-center d-flex flex-column justify-content-between">
                    <div class="text-secondary small fw-medium d-flex align-items-center justify-content-center" style="min-height: 2.5rem; line-height: 1.25;">
                        {{ $label }}
                    </div>
                    <div class="fs-2 fw-bold text-{{ $color }} mt-2 lh-1">{{ number_format($value) }}</div>
                </div>
            </div>
            @endforeach
        </div><div class="text-secondary small mt-3">Historical-positive candidates remain preserved in the frozen input; they are excluded only from allocation consideration.</div></div>
    </div>
    @endif

    <div class="card">
        <div class="card-header"><h3 class="card-title">Allocation Runs</h3></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Version</th><th>Stage</th><th>Status</th><th class="text-center">Candidates</th><th class="text-center">Allocated</th><th class="text-center">Quota Allocated</th><th class="text-center">CFF</th><th class="text-center">EM</th><th class="text-center">PHC</th><th class="text-center">NM</th><th class="text-center">Shifted</th><th></th></tr></thead><tbody>
        @forelse($runs as $r)<tr><td><strong>v{{ $r->version }}</strong></td><td>{{ $r->phase }}</td><td><span class="badge bg-blue-lt">{{ strtoupper($r->status) }}</span>@if($r->is_stale) <span class="badge bg-red-lt">STALE</span>@endif</td><td class="text-center">{{ number_format($r->total_candidates) }}</td><td class="text-center"><strong>{{ number_format($r->total_allocated) }}</strong></td><td class="text-center"><strong>{{ number_format($r->quota_allocated_count ?? 0) }}</strong></td><td class="text-center">{{ number_format($r->cff_allocated_count ?? 0) }}</td><td class="text-center">{{ number_format($r->em_allocated_count ?? 0) }}</td><td class="text-center">{{ number_format($r->phc_allocated_count ?? 0) }}</td><td class="text-center">{{ number_format($r->nm_count ?? 0) }}</td><td class="text-center">{{ number_format($r->shifted_count ?? 0) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('non-cadre.allocation.run',$r->id) }}">Open</a></td></tr>
        @empty<tr><td colspan="12" class="text-center text-secondary py-4">No NC4 run yet.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>
@endsection
