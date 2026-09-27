@extends('layouts.app')
@section('title','NC5 — Candidate Detail')
@section('page-header')
@php
    $r=$data['input']; $choice=$data['choice']; $allocation=$data['allocation']; $merit=$data['merit']; $tabulation=$data['tabulation']; $posts=$data['posts'];
    $decode=fn($v)=>array_values(array_filter(json_decode((string)$v,true)?:[],fn($x)=>filled($x)));
    $quota=collect(['CFF'=>$r->has_cff,'EM'=>$r->has_em,'PHC'=>$r->has_phc])->filter()->keys()->implode(', ') ?: 'Non Quota';
    $lane=function(array $choices)use($posts,$allocation){return collect($choices)->values()->map(function($code,$i)use($posts,$allocation){$p=$posts->get((string)$code);$allocated=$allocation && (string)$allocation->post_code===(string)$code;return '<span class="nc-choice-chip '.($allocated?'nc-choice-allocated':'').'">#'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'<strong>'.e((string)$code).'</strong><small>'.e((string)($p->post_title??'')).'</small></span>'; })->implode('');};
@endphp
<div class="row g-2 align-items-center"><div class="col"><div class="d-flex align-items-center gap-2 flex-wrap"><h2 class="page-title mb-0">{{ $r->reg }} — {{ $r->name }}</h2>@if($allocation?->post_code)<span class="badge bg-success-lt">ALLOCATED TO {{ $allocation->post_code }} — {{ $allocation->post_title }}</span>@elseif($r->historical_excluded)<span class="badge bg-warning-lt">HISTORICALLY EXCLUDED</span>@else<span class="badge bg-secondary-lt">NOT ALLOCATED</span>@endif</div><div class="text-secondary mt-1">Consolidated read-only view of the candidate's finalized Non-Cadre choice, eligibility and allocation authority.</div></div><div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('non-cadre.reporting.candidates') }}">Back to Candidate Search</a></div></div>
@endsection
@section('content')
<style>
.nc-choice-lane{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:.3rem}.nc-choice-chip{min-width:0;padding:.28rem .2rem;border:1px solid var(--tblr-border-color);border-radius:.35rem;text-align:center;line-height:1.1;background:var(--tblr-bg-surface);display:flex;flex-direction:column}.nc-choice-chip strong{font-size:.75rem}.nc-choice-chip small{font-size:.62rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.nc-choice-allocated{background:var(--tblr-red-lt,#ffe3e3);border-color:var(--tblr-red,#d63939);color:var(--tblr-red,#d63939)}
@media(max-width:1199.98px){.nc-choice-lane{grid-template-columns:repeat(5,minmax(0,1fr))}}@media(max-width:767.98px){.nc-choice-lane{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<div class="row row-cards">
<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Candidate Information</h3></div><div class="card-body"><dl class="row mb-0"><dt class="col-5">Reg / User</dt><dd class="col-7">{{ $r->reg }} / {{ $r->user_id }}</dd><dt class="col-5">Name</dt><dd class="col-7">{{ $r->name }}</dd><dt class="col-5">DOB</dt><dd class="col-7">{{ $r->birth_date ?: '—' }}</dd><dt class="col-5">Common Merit</dt><dd class="col-7 fw-bold">{{ $r->common_merit_position }}</dd><dt class="col-5">Quota</dt><dd class="col-7"><span class="badge bg-{{ $quota==='Non Quota'?'secondary':'azure' }}-lt">{{ $quota }}</span></dd></dl></div></div></div>
<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Merit Context</h3></div><div class="card-body">@if($merit)<dl class="row mb-0"><dt class="col-5">Common Merit</dt><dd class="col-7">{{ $merit->common_merit_position ?? '—' }}</dd><dt class="col-5">General Merit</dt><dd class="col-7">{{ $merit->general_merit_position ?? '—' }}</dd><dt class="col-5">Technical Merit</dt><dd class="col-7">{{ $merit->technical_merit_position ?? '—' }}</dd></dl>@else<span class="text-secondary">No Merit record found.</span>@endif</div></div></div>

<div class="col-12"><div class="card"><div class="card-header"><div><h3 class="card-title">Finalized Tabulation Ranking Inputs</h3><div class="card-subtitle">The exact finalized Tabulation academic inputs used by the finalized Merit run.</div></div></div>
@if($tabulation)
<div class="table-responsive"><table class="table table-vcenter table-bordered mb-0"><tbody>
<tr><th class="w-40">Written Qualified Track</th><td>{{ strtoupper((string)$tabulation->written_qualified_track) }}</td></tr>
<tr><th>Preliminary Mark</th><td>{{ $tabulation->preliminary_mark ?? '—' }}</td></tr>
<tr><th>General / Technical Written</th><td>{{ $tabulation->general_written_total ?? '—' }} / {{ $tabulation->technical_written_total ?? '—' }}</td></tr>
<tr><th>Viva Mark</th><td>{{ $tabulation->viva_mark ?? '—' }}</td></tr>
<tr><th>General / Technical Grand Total</th><td>{{ $tabulation->generalGrandTotalDisplay() }} / {{ $tabulation->technicalGrandTotalDisplay() }}</td></tr>
<tr><th>General / Technical Merit Eligible</th><td>{{ $tabulation->general_merit_eligible ? 'YES' : 'NO' }} / {{ $tabulation->technical_merit_eligible ? 'YES' : 'NO' }}</td></tr>
<tr><th>Birth Date Snapshot</th><td>{{ $tabulation->birth_date?->format('Y-m-d') ?? '—' }}</td></tr>
<tr><th>Graduation Year Snapshot</th><td>{{ $tabulation->graduation_year ?? '—' }}</td></tr>
<tr><th>Tabulation Version</th><td>v{{ $tabulation->processing_version }}</td></tr>
</tbody></table></div>
@else
<div class="card-body text-secondary">Finalized Tabulation ranking inputs could not be resolved from the finalized Merit source snapshot.</div>
@endif
</div></div>

<div class="col-12"><div class="card"><div class="card-header"><div><h3 class="card-title">Non-Cadre Choice Authority</h3><div class="card-subtitle">Imported choice through validated, effective and frozen Allocation Ready Choice.</div></div></div><div class="card-body">
@foreach([['Original Choice',$decode($choice?->original_choices)],['Validated Choice',$decode($choice?->validated_choices)],['Effective Choice',$decode($choice?->effective_choices)],['Allocation Ready Choice',$decode($r->allocation_ready_choices)]] as [$label,$choices])<div class="mb-3"><div class="fw-semibold mb-2">{{ $label }}</div><div class="nc-choice-lane">{!! $lane($choices) !!}</div>@if(empty($choices))<span class="text-secondary">—</span>@endif</div>@endforeach
@if($choice)<div class="small text-secondary">NC3 validation status: <strong>{{ strtoupper((string)$choice->validation_status) }}</strong></div>@endif
</div></div></div>

<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">NC4 Eligibility</h3></div><div class="card-body">@if($r->historical_excluded)<div class="badge bg-warning-lt mb-3">EXCLUDED FROM ALLOCATION</div><div class="fw-semibold">Reason</div><div>{{ $r->historical_exclusion_reason ?: 'Confirmed historical source match.' }}</div>@else<div class="badge bg-success-lt mb-3">ALLOCATION ELIGIBLE</div><div class="text-secondary">No confirmed historical exclusion was frozen for this candidate.</div>@endif</div></div></div>
<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Final Non-Cadre Allocation</h3></div><div class="card-body">@if($allocation?->post_code)<div class="badge bg-success-lt mb-3">ALLOCATED</div><dl class="row mb-0"><dt class="col-5">Post</dt><dd class="col-7"><strong>{{ $allocation->post_code }} — {{ $allocation->post_title }}</strong></dd><dt class="col-5">Organization</dt><dd class="col-7">{{ $allocation->entity ?: '—' }}</dd><dt class="col-5">Ministry</dt><dd class="col-7">{{ $allocation->ministry ?: '—' }}</dd><dt class="col-5">Choice Position</dt><dd class="col-7">{{ $allocation->choice_position ?: '—' }}</dd><dt class="col-5">Allocation Basis</dt><dd class="col-7">{{ strtoupper((string)$allocation->allocation_basis) }}</dd><dt class="col-5">Decision</dt><dd class="col-7">{{ strtoupper((string)$allocation->decision_status) }}</dd></dl>@else<div class="badge bg-secondary-lt mb-3">NOT ALLOCATED</div>@if($allocation?->decision_reason)<div>{{ $allocation->decision_reason }}</div>@elseif($r->historical_exclusion_reason)<div>{{ $r->historical_exclusion_reason }}</div>@endif @endif</div></div></div>

@if($data['adjustments']->isNotEmpty())<div class="col-12"><div class="card"><div class="card-header"><h3 class="card-title">Manual Choice Adjustment Audit</h3></div><div class="table-responsive"><table class="table table-bordered table-sm mb-0"><thead><tr><th>Time</th><th>Reason</th><th>Before</th><th>After</th></tr></thead><tbody>@foreach($data['adjustments'] as $a)<tr><td>{{ $a->created_at }}</td><td>{{ $a->reason }}</td><td>{{ implode(', ',$decode($a->before_choices)) }}</td><td>{{ implode(', ',$decode($a->after_choices)) }}</td></tr>@endforeach</tbody></table></div></div></div>@endif
</div>
@endsection
