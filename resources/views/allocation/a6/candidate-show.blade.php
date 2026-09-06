@extends('layouts.app')
@section('content')
@php
    $r = $data['registration'];
    $allocation = $data['allocation'];
    $allocationAbbr = $data['allocation_abbr'] ?? '—';
    $allocationStatus = strtoupper((string)($data['allocation_status'] ?? ''));
    $disposition = $data['disposition'] ?? null;
    $refs = $data['registration_reference'] ?? [];
    $choice = $data['choice_reporting'] ?? [];

    $enumText = static function ($value): string {
        if ($value instanceof \BackedEnum) $value = $value->value;
        elseif ($value instanceof \UnitEnum) $value = $value->name;
        return strtoupper((string)($value ?? '—'));
    };
    $badgeClass = static function ($value): string {
        $v = strtoupper((string)($value instanceof \BackedEnum ? $value->value : $value));
        return match (true) {
            in_array($v, ['PASS','PASSED','ACTIVE','APPEARED','VALID','FINALIZED','YES'], true) => 'success',
            in_array($v, ['FAIL','FAILED','ABSENT','INVALID','REJECTED','NO'], true) => 'danger',
            str_contains($v, 'TEMP') || str_contains($v, 'PENDING') => 'warning',
            default => 'secondary',
        };
    };
    $statusBadge = static function ($value) use ($enumText, $badgeClass): string {
        $text = $enumText($value);
        return '<span class="badge bg-'.$badgeClass($value).'-lt">'.e($text).'</span>';
    };
    $quotaText = static function (...$flags): string {
        $labels = ['CFF','EM','PHC'];
        $active = [];
        foreach ($flags as $i => $flag) if ((bool)$flag) $active[] = $labels[$i];
        return $active ? implode(' / ', $active) : 'NON QUOTA';
    };
    $category = $r->cadre_category;
    $categoryDisplay = $category instanceof \App\Enums\CadreCategory
        ? $category->value.' - '.$category->code()
        : ((string)($category ?? '—'));
    $choiceLane = static function (array $codes, $abbr, ?int $highlightCadreCode = null): string {
        if (!$codes) return '<span class="text-secondary">—</span>';

        return collect($codes)->map(function($code) use ($abbr, $highlightCadreCode) {
            $c = (int)$code;
            $highlight = $highlightCadreCode !== null && $c === $highlightCadreCode;
            $chipClass = $highlight ? 'a6-choice-chip a6-choice-chip-allocated' : 'a6-choice-chip';
            $abbrClass = $highlight ? 'small fw-semibold' : 'small text-secondary';

            return '<div class="'.$chipClass.'"><div class="fw-bold">'.e((string)$code).'</div><div class="'.$abbrClass.'">'.e((string)$abbr->get($c,'—')).'</div></div>';
        })->implode('');
    };
@endphp
<style>
.a6-choice-block{margin-bottom:1rem}
.a6-choice-title{font-weight:600;margin-bottom:.4rem}
.a6-choice-lane{display:grid;grid-template-columns:repeat(20,minmax(0,1fr));gap:.3rem;width:100%;align-items:stretch}
.a6-choice-chip{min-width:0;padding:.3rem .15rem;border:1px solid var(--tblr-border-color);border-radius:.35rem;text-align:center;background:var(--tblr-bg-surface);line-height:1.15;overflow:hidden}
.a6-choice-chip .small{font-size:.68rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
.a6-choice-chip-allocated{background:var(--tblr-success-lt,#d9f7e8);border-color:var(--tblr-success,#2fb344);color:var(--tblr-success,#2fb344);box-shadow:inset 0 0 0 1px var(--tblr-success,#2fb344)}
@media (max-width:1199.98px){.a6-choice-lane{grid-template-columns:repeat(10,minmax(0,1fr))}}
@media (max-width:767.98px){.a6-choice-lane{grid-template-columns:repeat(5,minmax(0,1fr))}}
</style>
<div class="page-header"><div class="container-xl"><div class="row align-items-center"><div class="col"><div class="d-flex align-items-center gap-2 flex-wrap"><h2 class="page-title mb-0">{{ $r->reg }} — {{ $r->name }}</h2>@if($allocation)<span class="badge bg-success-lt">ALLOCATED TO {{ $allocation->cadre_code }} - {{ $allocationAbbr }}</span>@else<span class="badge bg-warning-lt">NOT ALLOCATED</span>@endif</div><div class="text-secondary mt-1">Consolidated read-only view from Registration through final Allocation validation.</div></div><div class="col-auto ms-auto"><a class="btn btn-outline-secondary" href="{{ route('allocation.a6.candidates') }}">Back to Candidate Search</a></div></div></div></div>
<div class="page-body"><div class="container-xl"><div class="row row-cards">

<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Registration</h3></div><div class="card-body"><dl class="row mb-0"><dt class="col-5">Reg / User</dt><dd class="col-7">{{ $r->reg }} / {{ $r->user_id }}</dd><dt class="col-5">Name</dt><dd class="col-7">{{ $r->name }}</dd><dt class="col-5">DOB</dt><dd class="col-7">{{ $r->birth_date?->format('d-m-Y') }}</dd><dt class="col-5">Category</dt><dd class="col-7">{{ $categoryDisplay }}</dd><dt class="col-5">Sex</dt><dd class="col-7">{{ $refs['sex'] ?? '—' }}</dd><dt class="col-5">District</dt><dd class="col-7">{{ $refs['district'] ?? '—' }}</dd><dt class="col-5">Bachelor Subject</dt><dd class="col-7">{{ $refs['bachelor'] ?? '—' }}</dd><dt class="col-5">PRS</dt><dd class="col-7">{{ $refs['prs'] ?? '—' }}</dd><dt class="col-5">Quota</dt><dd class="col-7"><span class="badge bg-{{ ($r->has_ff_quota||$r->has_em_quota||$r->has_phc_quota)?'azure':'secondary' }}-lt">{{ $quotaText($r->has_ff_quota,$r->has_em_quota,$r->has_phc_quota) }}</span></dd></dl></div></div></div>

<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Preliminary</h3></div><div class="card-body">@if($x=$data['preliminary'])<dl class="row mb-0"><dt class="col-5">Mark</dt><dd class="col-7">{{ $x->mark }}</dd><dt class="col-5">Candidate Status</dt><dd class="col-7">{!! $statusBadge($x->candidate_status) !!}</dd><dt class="col-5">Result</dt><dd class="col-7">{!! $statusBadge($x->result_status) !!}</dd></dl>@else<span class="text-secondary">No record.</span>@endif</div></div></div>

<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Written</h3></div><div class="card-body">@if($x=$data['written'])<dl class="row mb-0"><dt class="col-5">Qualified Track</dt><dd class="col-7"><span class="badge bg-azure-lt">{{ $enumText($x->written_qualified_track) }}</span></dd><dt class="col-5">General Total</dt><dd class="col-7">{{ $x->general_counted_total ?? '—' }}</dd><dt class="col-5">Technical Total</dt><dd class="col-7">{{ $x->technical_counted_total ?? '—' }}</dd><dt class="col-5">General Result</dt><dd class="col-7">{!! $statusBadge($x->general_result_status) !!}</dd><dt class="col-5">Technical Result</dt><dd class="col-7">{!! $statusBadge($x->technical_result_status) !!}</dd></dl>@else<span class="text-secondary">No record.</span>@endif</div></div></div>

<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Viva</h3></div><div class="card-body">@if($x=$data['viva'])<dl class="row mb-0"><dt class="col-5">Attendance</dt><dd class="col-7">{!! $statusBadge($x->attendance_status) !!}</dd><dt class="col-5">Mark</dt><dd class="col-7">{{ $x->mark }}</dd><dt class="col-5">Result</dt><dd class="col-7">{!! $statusBadge($x->viva_result_status) !!}</dd><dt class="col-5">Viva Quota</dt><dd class="col-7"><span class="badge bg-{{ ($x->viva_cff||$x->viva_em||$x->viva_phc)?'azure':'secondary' }}-lt">{{ $quotaText($x->viva_cff,$x->viva_em,$x->viva_phc) }}</span></dd></dl>@else<span class="text-secondary">No record.</span>@endif</div></div></div>

<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Tabulation</h3></div><div class="card-body">@if($x=$data['tabulation'])<dl class="row mb-0"><dt class="col-5">Preliminary</dt><dd class="col-7">{{ $x->preliminary_mark }}</dd><dt class="col-5">General Written</dt><dd class="col-7">{{ $x->general_written_total ?? '—' }}</dd><dt class="col-5">Technical Written</dt><dd class="col-7">{{ $x->technical_written_total ?? '—' }}</dd><dt class="col-5">Viva</dt><dd class="col-7">{{ $x->viva_mark }}</dd><dt class="col-5">General Grand Total</dt><dd class="col-7">{{ $x->general_grand_total ?? '—' }}</dd><dt class="col-5">Technical Grand Total</dt><dd class="col-7">{{ $x->technical_grand_total ?? '—' }}</dd></dl>@else<span class="text-secondary">No current finalized record.</span>@endif</div></div></div>

<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Merit</h3></div><div class="card-body">@if($x=$data['merit'])<dl class="row mb-0"><dt class="col-5">Common Merit</dt><dd class="col-7">{{ $x->common_merit_position ?? '—' }}</dd><dt class="col-5">General Merit</dt><dd class="col-7">{{ $x->general_merit_position ?? '—' }}</dd><dt class="col-5">Technical Merit</dt><dd class="col-7">{{ $x->technical_merit_position ?? '—' }}</dd><dt class="col-5">Cadre Merit Map</dt><dd class="col-7 small"><code>{{ \App\Models\MeritResult::allMeritTechJson($x->all_merit_tech) }}</code></dd></dl>@else<span class="text-secondary">No current finalized Merit record.</span>@endif</div></div></div>

<div class="col-12"><div class="card"><div class="card-header"><div><h3 class="card-title">Choice Validation / Optimization</h3><div class="card-subtitle">Registration source through final allocation-ready Effective Choice.</div></div></div><div class="card-body">
    @foreach([['Registration Choice','registration'],['Validated Choice','validated'],['OMR Choice','omr'],['Effective Choice','effective']] as [$label,$key])
        @php
            $effectiveAllocatedCode = ($key === 'effective' && $allocation) ? (int)$allocation->cadre_code : null;
        @endphp
        <div class="a6-choice-block">
            <div class="a6-choice-title">{{ $label }}</div>
            <div class="a6-choice-lane">{!! $choiceLane((array)($choice[$key] ?? []), $choice['abbr'] ?? collect(), $effectiveAllocatedCode) !!}</div>
        </div>
    @endforeach
    <hr class="my-3"><div class="fw-semibold mb-2">Change Summary</div>
    @if(($choice['summary'] ?? collect())->isNotEmpty())<ul class="mb-0 ps-3">@foreach($choice['summary'] as $summary)<li class="mb-1">{{ $summary }}</li>@endforeach</ul>@else<div class="text-secondary">No recorded transformation/cut-off reason. Effective Choice follows the available validated source.</div>@endif
    @if($x=$data['choice_validation'])<div class="small text-secondary mt-3">Choice Validation v{{ $x->validation_version }} · {{ strtoupper((string)$x->status) }}</div>@endif
</div></div></div>

<div class="col-md-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Final Allocation &amp; A5 Validity</h3></div><div class="card-body">@if($x=$data['allocation'])<div class="mb-3 d-flex gap-2 flex-wrap"><span class="badge bg-success-lt">ALLOCATED — {{ $x->cadre_code }} - {{ $allocationAbbr }}</span><span class="badge bg-{{ $allocationStatus==='ACTIVE'?'success':($allocationStatus==='WITHHELD'?'warning':'danger') }}-lt">{{ $allocationStatus }}</span></div><dl class="row mb-0"><dt class="col-5">Cadre</dt><dd class="col-7"><strong>{{ $x->cadre_code }} - {{ $allocationAbbr }}</strong></dd><dt class="col-5">Publication Status</dt><dd class="col-7">{{ $allocationStatus }}</dd>@if($disposition)<dt class="col-5">Disposition Reason</dt><dd class="col-7">{{ $disposition->reason }}</dd>@endif<dt class="col-5">Merit Position</dt><dd class="col-7">{{ $x->merit_position }}</dd><dt class="col-5">Choice Position</dt><dd class="col-7">{{ $x->choice_position }}</dd><dt class="col-5">Basis</dt><dd class="col-7">{{ strtoupper((string)$x->allocation_basis) }}</dd><dt class="col-5">Movement</dt><dd class="col-7">{{ strtoupper((string)$x->movement_type) }}</dd></dl>@else<div class="badge bg-warning-lt">NOT ALLOCATED</div>@endif @if($v=$data['a5'])<hr><div class="d-flex gap-2 flex-wrap"><span class="badge bg-{{ $v->overall_status==='PASS'?'success':'danger' }}-lt">A5 {{ strtoupper((string)$v->overall_status) }}</span><span class="badge bg-{{ $v->bachelor_status==='PASS'?'success':'secondary' }}-lt">Bachelor {{ strtoupper((string)$v->bachelor_status) }}</span><span class="badge bg-{{ $v->prs_status==='PASS'?'success':'secondary' }}-lt">PRS {{ strtoupper((string)$v->prs_status) }}</span><span class="badge bg-{{ $v->technical_status==='PASS'?'success':'secondary' }}-lt">Technical {{ strtoupper((string)$v->technical_status) }}</span><span class="badge bg-{{ $v->quota_status==='PASS'?'success':'secondary' }}-lt">Quota {{ strtoupper((string)$v->quota_status) }}</span></div>@endif</div></div></div>
</div></div></div>
@endsection
